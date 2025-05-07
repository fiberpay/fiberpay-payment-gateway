const { registerPaymentMethod } = wc.wcBlocksRegistry;
const { getSetting } = wc.wcSettings;
const { createElement, useEffect, useState, useCallback, memo } = window.wp.element;

const settings = getSetting('fiberpay_payments_data', {});
console.log('v3');

const FiberpayComponent = (props) => {
    const { eventRegistration, emitResponse } = props;

    // Log all props to check if their identity changes often
    console.log('Fiberpay component props:', props);

    // Local state to track registration
    const [registered, setRegistered] = useState(false);

    // Callback to register payment setup
    const paymentSetupHandler = useCallback(() => {
        console.log('Fiberpay onPaymentSetup triggered');
        return {
            type: emitResponse.responseTypes.SUCCESS,
            meta: {
                paymentMethodData: {
                    payment_method: 'fiberpay_payments'
                }
            }
        };
    }, [emitResponse]);

    useEffect(() => {
        if (!registered) {
            console.log('Fiberpay registering payment setup');
            const unregister = eventRegistration.onPaymentSetup(paymentSetupHandler);
            setRegistered(true);

            return () => {
                if (typeof unregister === 'function') {
                    console.log('Fiberpay unregistering payment setup');
                    unregister();
                }
            };
        }
    }, [registered, eventRegistration, paymentSetupHandler]);

    return createElement(
        'div',
        { className: 'wc-block-components-payment-method-label' },
        settings.title || 'Fiberpay',
        settings.description &&
            createElement('div', {
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
