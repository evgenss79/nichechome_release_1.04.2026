<?php
/**
 * Email translations - English
 */

return [
    // Order confirmation email
    'email.order.subject' => 'Order Confirmation #{order_id} — NicheHome.ch',
    'email.order.title' => 'Thank You for Your Order!',
    'email.order.greeting' => 'Dear {customer_name},',
    'email.order.intro' => 'We have received your order and it is being processed. Thank you for shopping with NicheHome.ch!',
    'email.order.orderDetails' => 'Order Details',
    'email.order.orderNumber' => 'Order Number',
    'email.order.orderDate' => 'Order Date',
    'email.order.orderStatus' => 'Order Status',
    'email.order.paymentStatus' => 'Payment Status',
    'email.order.items' => 'Items',
    'email.order.product' => 'Product',
    'email.order.sku' => 'SKU',
    'email.order.qty' => 'Qty',
    'email.order.price' => 'Price',
    'email.order.total' => 'Total',
    'email.order.subtotal' => 'Subtotal',
    'email.order.shipping' => 'Shipping',
    'email.order.grandTotal' => 'Grand Total',
    'email.order.paymentMethod' => 'Payment Method',
    'email.order.deliveryMethod' => 'Delivery Method',
    'email.order.pickupBranch' => 'Pickup Location',
    'email.order.shippingAddress' => 'Shipping Address',
    'email.order.branch' => 'Branch',
    'email.order.address' => 'Address',
    'email.order.street' => 'Street',
    'email.order.city' => 'City',
    'email.order.country' => 'Country',
    'email.order.footer.thanks' => 'Thank you for shopping with NicheHome.ch!',
    'email.order.footer.questions' => 'If you have any questions, please contact us at support@nichehome.ch',
    'email.footer.auto' => 'This is an automated message. Please do not reply to this email.',
    
    // Payment methods
    'email.payment.cash' => 'Cash (on pickup)',
    'email.payment.twint' => 'TWINT',
    'email.payment.card' => 'Credit/Debit Card',
    'email.payment.paypal' => 'PayPal',
    
    // Delivery methods
    'email.delivery.delivery' => 'Delivery',
    'email.delivery.pickup' => 'Pickup',
    'email.delivery.free' => 'FREE',
    'email.delivery.pickupInBranch' => 'Pickup in branch',
    
    // Order statuses
    'email.status.pending' => 'Pending',
    'email.status.paid' => 'Paid',
    'email.status.awaiting_payment' => 'Awaiting Payment',
    'email.status.awaiting_cash_pickup' => 'Awaiting Cash Pickup',
    'email.status.completed' => 'Completed',
    'email.status.fulfilled' => 'Fulfilled',
    'email.status.pending_payment' => 'Pending Payment',
    'email.status.cash_on_pickup' => 'Cash on Pickup',
    
    // Support auto-reply email
    'email.support.subject' => 'We Received Your Request — NicheHome.ch',
    'email.support.title' => 'We Received Your Request',
    'email.support.greeting' => 'Dear {name},',
    'email.support.intro' => 'Thank you for contacting NicheHome.ch support. We have received your request and will respond as soon as possible.',
    'email.support.yourRequest' => 'Your Request',
    'email.support.subject_label' => 'Subject',
    'email.support.message_label' => 'Message',
    'email.support.responseTime' => 'Our support team typically responds within 24-48 hours during business days.',
    'email.support.footer.thanks' => 'Thank you for choosing NicheHome.ch',
    'email.support.footer.urgent' => 'For urgent matters, please call us directly',
    
    // Admin notification (kept in English)
    'email.admin.order.subject' => 'New Order #{order_id} — NicheHome.ch',
    'email.admin.order.title' => 'New Order Received',
    'email.admin.support.subject' => 'New Support Request — {name}',
    'email.admin.support.title' => 'New Support Request',
];
