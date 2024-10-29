const omsn_id = "akd-orange-money-sn"
const omsn_data = window.wc.wcSettings.getSetting( omsn_id + '_data', {} );
const omsn_label = window.wp.htmlEntities.decodeEntities( omsn_data.title );
//const omsn_label = Object( window.wp.element.createElement )( 'img', { src: omsn_data.icon, alt: omsn_label } ) : '';
//omsn_label += omsn_data.icon 
const omsn_content = ( omsn_data ) => {
	return window.wp.htmlEntities.decodeEntities( omsn_data.description || '' );
};
const OmSnGateway = {
	name: omsn_id,
	label: omsn_label,
	content: Object( window.wp.element.createElement )( omsn_content, null ),
	edit: Object( window.wp.element.createElement )( omsn_content, null ),
	canMakePayment: () => true,
	placeOrderButtonLabel: window.wp.i18n.__( 'Continue', omsn_id ),
	ariaLabel: omsn_label,
	supports: {
		features: omsn_data.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( OmSnGateway );