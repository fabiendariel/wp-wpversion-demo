
jQuery(document).ready(($) => {

	$('#btn_refresh_wpversion').on('click', () => {

		var data = {
			'action': 'refresh_datas'
		};

		$.post(ajaxurl, data, (response) => {
			$('#last_refresh').html('now');
		});

	});
	
});