const { registerPaymentMethod } = wc.wcBlocksRegistry;
const { getSetting } = wc.wcSettings;
const { createElement } = window.wp.element;

console.log('Fiberpay blocks.js loaded');

const settings = getSetting('fiberpay_payments_data', {});
console.log('Fiberpay settings loaded:', settings);

const FiberpayComponent = (props) => {
    console.log('Fiberpay component rendering with props:', props);
    const { eventRegistration, emitResponse } = props;
    const { onPaymentSetup } = eventRegistration;

    onPaymentSetup(() => {
        console.log('Fiberpay onPaymentSetup triggered');
        return {
            type: emitResponse.responseTypes.SUCCESS,
            meta: {
                paymentMethodData: {
                    payment_method: 'fiberpay_payments'
                }
            }
        };
    });

    return createElement('div', { className: 'wc-block-components-payment-method-label' },
        settings.title || 'Fiberpay',
        settings.description && createElement('div', {
            className: 'wc-block-components-payment-method-description',
            dangerouslySetInnerHTML: { __html: settings.description }
        })
    );
};

const paymentMethod = {
    name: 'fiberpay_payments',
    label: settings.title || 'Fiberpay',
    content: createElement(FiberpayComponent),
    edit: createElement(FiberpayComponent),
    canMakePayment: () => {
        console.log('Fiberpay canMakePayment called, returning true');
        return true;
    },
    ariaLabel: settings.title || 'Fiberpay Payment Method',
    supports: {
        features: settings.supports || ['products']
    },
};

console.log('Registering Fiberpay payment method:', paymentMethod);
registerPaymentMethod(paymentMethod);
