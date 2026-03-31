<?php
/**
 * Email translations - Russian (Русский)
 */

return [
    // Order confirmation email
    'email.order.subject' => 'Подтверждение заказа #{order_id} — NicheHome.ch',
    'email.order.title' => 'Спасибо за ваш заказ!',
    'email.order.greeting' => 'Уважаемый/ая {customer_name},',
    'email.order.intro' => 'Мы получили ваш заказ и обрабатываем его. Спасибо, что делаете покупки в NicheHome.ch!',
    'email.order.orderDetails' => 'Детали заказа',
    'email.order.orderNumber' => 'Номер заказа',
    'email.order.orderDate' => 'Дата заказа',
    'email.order.orderStatus' => 'Статус заказа',
    'email.order.paymentStatus' => 'Статус оплаты',
    'email.order.items' => 'Товары',
    'email.order.product' => 'Товар',
    'email.order.sku' => 'SKU',
    'email.order.qty' => 'Кол-во',
    'email.order.price' => 'Цена',
    'email.order.total' => 'Итого',
    'email.order.subtotal' => 'Промежуточный итог',
    'email.order.shipping' => 'Доставка',
    'email.order.grandTotal' => 'Общая сумма',
    'email.order.paymentMethod' => 'Способ оплаты',
    'email.order.deliveryMethod' => 'Способ доставки',
    'email.order.pickupBranch' => 'Место получения',
    'email.order.shippingAddress' => 'Адрес доставки',
    'email.order.branch' => 'Филиал',
    'email.order.address' => 'Адрес',
    'email.order.street' => 'Улица',
    'email.order.city' => 'Город',
    'email.order.country' => 'Страна',
    'email.order.footer.thanks' => 'Спасибо за покупку в NicheHome.ch!',
    'email.order.footer.questions' => 'Если у вас есть вопросы, свяжитесь с нами по адресу support@nichehome.ch',
    'email.footer.auto' => 'Это автоматическое сообщение. Пожалуйста, не отвечайте на это письмо.',
    
    // Payment methods
    'email.payment.cash' => 'Наличные (при получении)',
    'email.payment.twint' => 'TWINT',
    'email.payment.card' => 'Кредитная/дебетовая карта',
    'email.payment.paypal' => 'PayPal',
    
    // Delivery methods
    'email.delivery.delivery' => 'Доставка',
    'email.delivery.pickup' => 'Самовывоз',
    'email.delivery.free' => 'БЕСПЛАТНО',
    'email.delivery.pickupInBranch' => 'Самовывоз из филиала',
    
    // Order statuses
    'email.status.pending' => 'В ожидании',
    'email.status.paid' => 'Оплачено',
    'email.status.awaiting_payment' => 'Ожидание оплаты',
    'email.status.awaiting_cash_pickup' => 'Ожидание оплаты наличными при получении',
    'email.status.completed' => 'Завершено',
    'email.status.fulfilled' => 'Выполнено',
    'email.status.pending_payment' => 'Оплата в ожидании',
    'email.status.cash_on_pickup' => 'Наличные при получении',
    
    // Support auto-reply email
    'email.support.subject' => 'Мы получили ваш запрос — NicheHome.ch',
    'email.support.title' => 'Мы получили ваш запрос',
    'email.support.greeting' => 'Уважаемый/ая {name},',
    'email.support.intro' => 'Спасибо, что обратились в службу поддержки NicheHome.ch. Мы получили ваш запрос и ответим как можно скорее.',
    'email.support.yourRequest' => 'Ваш запрос',
    'email.support.subject_label' => 'Тема',
    'email.support.message_label' => 'Сообщение',
    'email.support.responseTime' => 'Наша служба поддержки обычно отвечает в течение 24-48 часов в рабочие дни.',
    'email.support.footer.thanks' => 'Спасибо, что выбрали NicheHome.ch',
    'email.support.footer.urgent' => 'По срочным вопросам, пожалуйста, звоните нам напрямую',
    
    // Admin notification (kept in English)
    'email.admin.order.subject' => 'New Order #{order_id} — NicheHome.ch',
    'email.admin.order.title' => 'New Order Received',
    'email.admin.support.subject' => 'New Support Request — {name}',
    'email.admin.support.title' => 'New Support Request',
];
