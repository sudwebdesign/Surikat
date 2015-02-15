<!--#include virtual="../plugin/ckeditor/ckeditor.js" -->
$js([
	'jquery',
	'plugin/ckeditor/ckeditor.js'
],function(){
	$('[is=ckeditor]').each(function(){
		$(this).wrap('<div/>');
		
		CKEDITOR.basePath = $('base').attr('href')+'js/plugin/ckeditor/';//moved in js/ 4 resolve 1 incompréhensive bug
		alert('cqcb: '+CKEDITOR.basePath);
//		CKEDITOR.basePath = CKEDITOR.basePath.replace(/\/js/i, '');
		if(console) console.info(CKEDITOR.basePath);
		CKEDITOR.replace(this);
	});
});
//http://localhost/Autonomous/Surikat/js/plugin/ckeditor/ckeditor.js ok
//http://localhost/Autonomous/js/plugin/ckeditor/ckeditor.js  mirroring ok
