window.Site = {
  brand: {name:'Pet Wholesale Hub',email:'',currency:'USD',minimum_quantity:30,quantity_step:5},
  image(value) {
    if(typeof value!=='string' || /[<>"'\s\\]/.test(value))return 'assets/placeholder.svg';
    if(/^https:\/\//i.test(value))return value;
    if(/^(?:\/?(?:[A-Za-z0-9_-]+\/)*)(?:uploads\/|assets\/)/.test(value) && !value.includes('..'))return value;
    return 'assets/placeholder.svg';
  },
  money(value){try{return new Intl.NumberFormat('en-US',{style:'currency',currency:this.brand.currency}).format(Number(value)||0)}catch(e){return Number(value||0).toFixed(2)}},
  minimum(){return Number(this.brand.minimum_quantity)||30},
  step(){return Number(this.brand.quantity_step)||5},
  quantity(value){const min=this.minimum(),step=this.step();return min+Math.max(0,Math.ceil(((parseInt(value,10)||min)-min)/step))*step},
  quote(text){if(!this.brand.email || this.brand.email.endsWith('@example.com')){alert('The store owner has not configured a contact email yet.');return}location.href='mailto:'+encodeURIComponent(this.brand.email)+'?subject='+encodeURIComponent('Wholesale inquiry')+'&body='+encodeURIComponent(text+'\n\nPrices and availability are subject to confirmation.');}
};
document.addEventListener('DOMContentLoaded',async()=>{
 document.querySelectorAll('img').forEach(img=>img.addEventListener('error',()=>{if(!img.src.endsWith('/assets/placeholder.svg'))img.src='assets/placeholder.svg';},{once:true}));
 try{
  const r=await fetch('get_brand.php',{cache:'no-store'});const j=await r.json();if(!r.ok||!j.success)throw Error('Store configuration unavailable');Site.brand=j.data;
  document.querySelectorAll('.logo-text,.footer-title').forEach(e=>e.textContent=j.data.name);
  document.querySelectorAll('.logo-icon').forEach(e=>{const img=document.createElement('img');img.src=Site.image(j.data.logo);img.alt='';img.width=30;img.height=30;e.replaceChildren(img)});
  document.querySelectorAll('.hero-title').forEach(e=>e.textContent=j.data.name);
  document.querySelectorAll('.hero-subtitle,.contact-hero > p').forEach(e=>e.textContent=j.data.intro);
  document.querySelectorAll('.why-box').forEach(e=>{const h=document.createElement('h3');h.textContent='About us';const p=document.createElement('p');p.textContent=j.data.about;e.replaceChildren(h,p)});
  document.querySelectorAll('.why-photo img,.pet-track img').forEach(e=>e.src=Site.image(j.data.hero_image));
  document.querySelectorAll('a.email-link,[data-contact-email]').forEach(e=>{e.textContent=j.data.email;e.href='mailto:'+j.data.email});
  document.querySelectorAll('.footer').forEach(e=>{const text=document.createElement('p');text.textContent=j.data.footer;const a=document.createElement('a');a.textContent=j.data.email;a.href='mailto:'+j.data.email;e.replaceChildren(text,a)});
  if(/^#[0-9a-f]{6}$/i.test(j.data.primary))document.documentElement.style.setProperty('--primary',j.data.primary);
  if(/^#[0-9a-f]{6}$/i.test(j.data.background))document.documentElement.style.setProperty('--background',j.data.background);
  document.querySelectorAll('#qty,#calcQty').forEach(e=>{e.min=Site.minimum();e.step=Site.step();e.value=Site.minimum()});
  window.dispatchEvent(new Event('brand-ready'));
 }catch(e){const message=document.createElement('p');message.className='site-error';message.textContent='Store configuration unavailable. Please try again later.';document.body.prepend(message);}
 const featured=document.getElementById('featuredProducts');
 if(featured){try{const r=await fetch('get_products.php',{cache:'no-store'}),j=await r.json();if(!r.ok||!j.success)throw Error();featured.replaceChildren();const list=j.data.filter(p=>Number(p.featured)===1).slice(0,8);if(!list.length){featured.textContent='No featured products yet.';}for(const p of list){const a=document.createElement('a');a.className='scroll-item';a.href='product.html?id='+encodeURIComponent(p.id);const img=document.createElement('img');img.src=Site.image(p.images[0]);img.alt=p.name;const title=document.createElement('div');title.className='scroll-caption';title.textContent=p.name;a.append(img,title);featured.append(a)}}catch(e){featured.textContent='Products could not be loaded. Please try again.'}}
});
