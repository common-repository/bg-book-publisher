jQuery(document).ready( function($) {
	$('.ogl-link').click(e=>{
		$('#toc details:not([open]) summary').click();
	});
});
