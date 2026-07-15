1. For Ngrok run go to ngrok dashboar to run 
    run grok application
    ngrok config add-authtoken 3Brrk7mREVbtnxSS1JFdyhgInrx_6K7fCpPvRcyfQBRGoAUgy
    ngrok http 8000

2. For Strip webhook using this syntax in terminal
    stripe listen --forward-to localhost:8000/api/webhooks/stripe


//PaymentGateway Testing

RazorpayTest
MasterCard : 5120 4333 9011 9037
Visa : 4628 9499 7226 2986

StripeTest
Visa : 4242 4242 4242 4242
