<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Razorpay Checkout - {{ config('app.name') }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            background-color: #f4f7f6;
        }
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3399cc;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 2s linear infinite;
            margin-bottom: 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .message {
            color: #555;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <div class="loader"></div>
    <div class="message">Connecting to Razorpay Secure Gateway...</div>

    <form id="razorpay-form" action="{{ route('checkout') }}" method="GET">
        <input type="hidden" name="status" value="success">
        <input type="hidden" name="uuid" value="{{ $notes['order_uuid'] }}">
        <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id">
        <input type="hidden" name="razorpay_order_id" id="razorpay_order_id">
        <input type="hidden" name="razorpay_signature" id="razorpay_signature">
    </form>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        var options = {
            "key": "{{ $key }}",
            "amount": "{{ $amount }}",
            "currency": "{{ $currency }}",
            "name": "{{ $name }}",
            "description": "Order #{{ $notes['order_uuid'] }}",
            "order_id": "{{ $order_id }}",
            "handler": function (response){
                document.getElementById('razorpay_payment_id').value = response.razorpay_payment_id;
                document.getElementById('razorpay_order_id').value = response.razorpay_order_id;
                document.getElementById('razorpay_signature').value = response.razorpay_signature;
                document.getElementById('razorpay-form').submit();
            },
            "prefill": {
                "name": "{{ auth()->user()->name ?? '' }}",
                "email": "{{ auth()->user()->email ?? '' }}"
            },
            "notes": {
                "order_uuid": "{{ $notes['order_uuid'] }}"
            },
            "theme": {
                "color": "#3399cc"
            },
            "modal": {
                "ondismiss": function(){
                    window.location.href = "{{ route('checkout') }}?status=cancel&uuid={{ $notes['order_uuid'] }}";
                }
            }
        };
        var rzp1 = new Razorpay(options);
        
        rzp1.on('payment.failed', function (response){
            window.location.href = "{{ route('checkout') }}?status=cancel&uuid={{ $notes['order_uuid'] }}";
        });

        window.onload = function(){
            rzp1.open();
        };
    </script>
</body>
</html>
