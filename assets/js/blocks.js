const { registerPaymentMethod } = wc.wcBlocksRegistry;

const paymentMethod = {
    name: 'fiberpay_payments',
    label: 'Fiberpay',
    content: <div>Pay with Fiberpay</div>,
    edit: <div>Pay with Fiberpay</div>,
    canMakePayment: () => true,
    ariaLabel: 'Fiberpay Payment Method',
    supports: {
        features: ['products', ]
    },
};

console.log('Registering Fiberpay payment method');
registerPaymentMethod(paymentMethod);
