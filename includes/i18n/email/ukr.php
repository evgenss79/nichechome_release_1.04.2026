<?php
/**
 * Email translations - Ukrainian (Українська)
 */

return [
    // Order confirmation email
    'email.order.subject' => 'Підтвердження замовлення #{order_id} — NicheHome.ch',
    'email.order.title' => 'Дякуємо за ваше замовлення!',
    'email.order.greeting' => 'Шановний/а {customer_name},',
    'email.order.intro' => 'Ми отримали ваше замовлення і обробляємо його. Дякуємо, що робите покупки в NicheHome.ch!',
    'email.order.orderDetails' => 'Деталі замовлення',
    'email.order.orderNumber' => 'Номер замовлення',
    'email.order.orderDate' => 'Дата замовлення',
    'email.order.orderStatus' => 'Статус замовлення',
    'email.order.paymentStatus' => 'Статус оплати',
    'email.order.items' => 'Товари',
    'email.order.product' => 'Товар',
    'email.order.sku' => 'SKU',
    'email.order.qty' => 'Кіл-ть',
    'email.order.price' => 'Ціна',
    'email.order.total' => 'Всього',
    'email.order.subtotal' => 'Проміжний підсумок',
    'email.order.shipping' => 'Доставка',
    'email.order.grandTotal' => 'Загальна сума',
    'email.order.paymentMethod' => 'Спосіб оплати',
    'email.order.deliveryMethod' => 'Спосіб доставки',
    'email.order.pickupBranch' => 'Місце отримання',
    'email.order.shippingAddress' => 'Адреса доставки',
    'email.order.branch' => 'Філія',
    'email.order.address' => 'Адреса',
    'email.order.street' => 'Вулиця',
    'email.order.city' => 'Місто',
    'email.order.country' => 'Країна',
    'email.order.footer.thanks' => 'Дякуємо за покупку в NicheHome.ch!',
    'email.order.footer.questions' => 'Якщо у вас є питання, зв\'яжіться з нами за адресою support@nichehome.ch',
    'email.footer.auto' => 'Це автоматичне повідомлення. Будь ласка, не відповідайте на цей лист.',
    
    // Payment methods
    'email.payment.cash' => 'Готівка (при отриманні)',
    'email.payment.twint' => 'TWINT',
    'email.payment.card' => 'Кредитна/дебетова картка',
    'email.payment.paypal' => 'PayPal',
    
    // Delivery methods
    'email.delivery.delivery' => 'Доставка',
    'email.delivery.pickup' => 'Самовивіз',
    'email.delivery.free' => 'БЕЗКОШТОВНО',
    'email.delivery.pickupInBranch' => 'Самовивіз з філії',
    
    // Order statuses
    'email.status.pending' => 'Очікування',
    'email.status.paid' => 'Оплачено',
    'email.status.awaiting_payment' => 'Очікування оплати',
    'email.status.awaiting_cash_pickup' => 'Очікування оплати готівкою при отриманні',
    'email.status.completed' => 'Завершено',
    'email.status.fulfilled' => 'Виконано',
    'email.status.pending_payment' => 'Оплата в очікуванні',
    'email.status.cash_on_pickup' => 'Готівка при отриманні',
    
    // Support auto-reply email
    'email.support.subject' => 'Ми отримали ваш запит — NicheHome.ch',
    'email.support.title' => 'Ми отримали ваш запит',
    'email.support.greeting' => 'Шановний/а {name},',
    'email.support.intro' => 'Дякуємо, що звернулися до служби підтримки NicheHome.ch. Ми отримали ваш запит і відповімо якомога швидше.',
    'email.support.yourRequest' => 'Ваш запит',
    'email.support.subject_label' => 'Тема',
    'email.support.message_label' => 'Повідомлення',
    'email.support.responseTime' => 'Наша служба підтримки зазвичай відповідає протягом 24-48 годин у робочі дні.',
    'email.support.footer.thanks' => 'Дякуємо, що обрали NicheHome.ch',
    'email.support.footer.urgent' => 'З термінових питань, будь ласка, телефонуйте нам безпосередньо',
    
    // Admin notification (kept in English)
    'email.admin.order.subject' => 'New Order #{order_id} — NicheHome.ch',
    'email.admin.order.title' => 'New Order Received',
    'email.admin.support.subject' => 'New Support Request — {name}',
    'email.admin.support.title' => 'New Support Request',
];
