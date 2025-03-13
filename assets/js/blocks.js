const { registerPaymentMethod } = wc.wcBlocksRegistry;
const { getSetting } = wc.wcSettings;

const settings = getSetting('fiberpay_payments_data', {});

const FiberpayComponent = (props) => {
    const { eventRegistration, emitResponse } = props;
    const { onPaymentSetup } = eventRegistration;

    onPaymentSetup(() => {
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
                        {settings.title}
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
    canMakePayment: () => true,
    ariaLabel: 'Fiberpay Payment Method',
    supports: {
        features: ['products', 'subscriptions', 'refunds', 'tokenization']
    },
};

console.log('Registering Fiberpay payment method');
registerPaymentMethod(paymentMethod);
