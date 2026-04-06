1. For Ngrok run go to ngrok dashboar to run 
    ngrok config add-authtoken 3Brrk7mREVbtnxSS1JFdyhgInrx_6K7fCpPvRcyfQBRGoAUgy
    ngrok http 8080

2. For Strip webhook using this syntax in terminal
    stripe listen --forward-to localhost:8000/api/webhooks/stripe


Need to do next:
Retry implementation, need to understand how to retry. retry_count in payment_log