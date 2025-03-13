const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { getSetting } = window.wc.wcSettings;

const settings = window.fiberpay_payments_data || {
    title: 'Fiberpay',
    description: 'Pay with Fiberpay',
    enabled: true,
};

const FiberpayComponent = () => {
    return window.wp.element.createElement('div', null, settings.description);
};

const fiberpayPaymentMethod = {
    name: 'fiberpay',
    label: settings.title,
    content: window.wp.element.createElement(FiberpayComponent, null),
    edit: window.wp.element.createElement(FiberpayComponent, null),
    canMakePayment: () => true,
    ariaLabel: settings.title,
    supports: {
        features: settings.supports || [],
    },
};

if (settings.enabled) {
    registerPaymentMethod(fiberpayPaymentMethod);
}
