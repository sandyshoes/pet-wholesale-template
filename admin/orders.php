<?php
require_once dirname(__DIR__).'/app/admin_layout.php'; admin_header('订单模块未启用');
?><h1>订单与支付尚未启用</h1><p>此版本为批发目录与询价模板。原订单数据保持原样，旧实现保存在本地私有备份中，正式交易功能需后续开发和验收。</p><?php admin_footer(); ?>