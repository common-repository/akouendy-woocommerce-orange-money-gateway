const wavesn_id = "akd-wave"
const wavesn_data = window.wc.wcSettings.getSetting( wavesn_id + '_data', {} );
const wavesn_label = window.wp.htmlEntities.decodeEntities( wavesn_data.title );
const wavesn_content = ( wavesn_data ) => {
	return window.wp.htmlEntities.decodeEntities( wavesn_data.description || '' );
};
const WaveGateway = {
	name: wavesn_id,
	label: wavesn_label,
	content: Object( window.wp.element.createElement )( wavesn_content, null ),
	edit: Object( window.wp.element.createElement )( wavesn_content, null ),
	canMakePayment: () => true,
	placeOrderButtonLabel: window.wp.i18n.__( 'Continue', wavesn_id ),
	ariaLabel: wavesn_label,
	supports: {
		features: wavesn_data.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( WaveGateway );