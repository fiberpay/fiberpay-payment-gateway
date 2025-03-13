const { registerPaymentMethod } = wc.wcBlocksRegistry;
const { getSetting } = wc.wcSettings;

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

    return (
        <div className="wc-block-checkout__payment-method">
            <div className="wc-block-components-radio-control">
                <div className="wc-block-components-radio-control__option">
                    <input
                        type="radio"
                        id="fiberpay-payment-method"
                        name="payment-method"
                        value="fiberpay_payments"
                        checked={true}
                    />
                    <label htmlFor="fiberpay-payment-method">
                        {settings.title || 'Fiberpay'}
                    </label>
                </div>
            </div>
            {settings.description && (
                <div className="wc-block-components-payment-method-description">
                    {settings.description}
                </div>
            )}
        </div>
    );
};

const paymentMethod = {
    name: 'fiberpay_payments',
    label: settings.title || 'Fiberpay',
    content: <FiberpayComponent />,
    edit: <FiberpayComponent />,
    canMakePayment: () => {
        console.log('Fiberpay canMakePayment called, returning true');
        return true;
    },
    ariaLabel: 'Fiberpay Payment Method',
    supports: {
        features: ['products', 'subscriptions', 'refunds', 'tokenization']
    },
};

console.log('Registering Fiberpay payment method:', paymentMethod);
registerPaymentMethod(paymentMethod);
