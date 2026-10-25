param(
    [Parameter(Mandatory=$true)][ValidateSet('Create','Verify','Extract')][string]$Mode,
    [Parameter(Mandatory=$true)][string]$Archive,
    [string]$Source,
    [string]$Destination
)
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
function FileDigest([byte[]]$Bytes) {
    $sha=[Security.Cryptography.SHA256]::Create()
    try { return ([BitConverter]::ToString($sha.ComputeHash($Bytes))).Replace('-','').ToLowerInvariant() } finally { $sha.Dispose() }
}
function SafeName([string]$Name) {
    if ($Name -notmatch '^[A-Za-z0-9_./-]+$' -or $Name -match '^/|(^|/)\.{1,2}(/|$)|/$|//' ) { throw 'Invalid archive filename' }
    foreach ($segment in $Name.Split('/')) {
        if ($segment -match '[. ]$|^(?i:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)') { throw 'Unsafe platform-specific archive filename' }
    }
    return $Name
}
if ($Mode -eq 'Create') {
    $sourceRoot=(Resolve-Path -LiteralPath $Source).Path
    if (Test-Path -LiteralPath $Archive) { throw 'Refusing to overwrite an existing archive' }
    $manifest=Get-Content -LiteralPath (Join-Path $sourceRoot 'RELEASE-MANIFEST.json') -Raw | ConvertFrom-Json
    $names=@($manifest.files | ForEach-Object { SafeName $_.path }) + 'RELEASE-MANIFEST.json'
    $actual=@(Get-ChildItem -LiteralPath $sourceRoot -File -Force -Recurse | ForEach-Object { [IO.Path]::GetRelativePath($sourceRoot,$_.FullName).Replace('\','/') })
    if (@(Compare-Object ($names | Sort-Object) ($actual | Sort-Object)).Count) { throw 'Unmanifested or missing file; refusing to archive' }
    $zip=[IO.Compression.ZipFile]::Open($Archive,[IO.Compression.ZipArchiveMode]::Create)
    try {
        foreach ($name in ($names | Sort-Object -CaseSensitive)) {
            $bytes=[IO.File]::ReadAllBytes((Join-Path $sourceRoot $name))
            if ($name -ne 'RELEASE-MANIFEST.json') {
                $expected=$manifest.files | Where-Object { $_.path -ceq $name }
                if ((FileDigest $bytes) -ne $expected.sha256) { throw 'Manifest digest mismatch' }
            }
            $entry=$zip.CreateEntry($name,[IO.Compression.CompressionLevel]::Optimal)
            $entry.LastWriteTime=[DateTimeOffset]::new(2000,1,1,0,0,0,[TimeSpan]::Zero)
            $stream=$entry.Open();try { $stream.Write($bytes,0,$bytes.Length) } finally { $stream.Dispose() }
        }
    } finally { $zip.Dispose() }
}
$zip=[IO.Compression.ZipFile]::OpenRead($Archive)
try {
    if ($zip.Entries.Count -gt 1000) { throw 'Too many archive entries' }
    $seen=@{}; $total=0
    foreach ($entry in $zip.Entries) {
        $name=SafeName $entry.FullName
        if ($seen.ContainsKey($name)) { throw 'Duplicate or case-colliding archive path' }
        if ((($entry.ExternalAttributes -shr 16) -band 61440) -eq 40960) { throw 'Symlink in archive' }
        $seen[$name]=$entry; $total+=$entry.Length
    }
    if ($total -gt 20971520) { throw 'Archive exceeds source size limit' }
    if (!$seen.ContainsKey('RELEASE-MANIFEST.json')) { throw 'Missing release manifest' }
    $reader=[IO.StreamReader]::new($seen['RELEASE-MANIFEST.json'].Open())
    try { $manifest=$reader.ReadToEnd() | ConvertFrom-Json } finally { $reader.Dispose() }
    if ($manifest.files.Count + 1 -ne $zip.Entries.Count) { throw 'Unexpected archive files' }
    $manifestSeen=@{}
    foreach ($file in $manifest.files) {
        $name=SafeName $file.path
        if ($name -eq 'RELEASE-MANIFEST.json' -or $manifestSeen.ContainsKey($name)) { throw 'Duplicate or invalid manifest path' }
        $manifestSeen[$name]=$true
        if (!$seen.ContainsKey($name)) { throw 'Missing archive file' }
        $stream=$seen[$name].Open();$buffer=[IO.MemoryStream]::new()
        try { $stream.CopyTo($buffer);$bytes=$buffer.ToArray() } finally { $stream.Dispose();$buffer.Dispose() }
        if ($bytes.Length -ne $file.bytes -or (FileDigest $bytes) -ne $file.sha256) { throw 'Archive contents do not match manifest' }
    }
    if ($Mode -eq 'Extract') {
        if (Test-Path -LiteralPath $Destination) { throw 'Extraction destination must not already exist' }
        [IO.Directory]::CreateDirectory([IO.Path]::GetFullPath($Destination)) | Out-Null
        foreach ($entry in $zip.Entries) {
            $output=Join-Path $Destination $entry.FullName
            [IO.Directory]::CreateDirectory([IO.Path]::GetDirectoryName([IO.Path]::GetFullPath($output))) | Out-Null
            [IO.Compression.ZipFileExtensions]::ExtractToFile($entry,$output,$false)
        }
    }
    [PSCustomObject]@{Mode=$Mode;Files=$zip.Entries.Count;Bytes=$total;SHA256=(Get-FileHash -LiteralPath $Archive -Algorithm SHA256).Hash.ToLowerInvariant()} | ConvertTo-Json -Compress
} finally { $zip.Dispose() }
