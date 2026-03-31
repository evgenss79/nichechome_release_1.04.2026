<?php
/**
 * Email translations - German (Deutsch)
 */

return [
    // Order confirmation email
    'email.order.subject' => 'Bestellbestätigung #{order_id} — NicheHome.ch',
    'email.order.title' => 'Vielen Dank für Ihre Bestellung!',
    'email.order.greeting' => 'Sehr geehrte/r {customer_name},',
    'email.order.intro' => 'Wir haben Ihre Bestellung erhalten und bearbeiten sie. Vielen Dank, dass Sie bei NicheHome.ch einkaufen!',
    'email.order.orderDetails' => 'Bestelldetails',
    'email.order.orderNumber' => 'Bestellnummer',
    'email.order.orderDate' => 'Bestelldatum',
    'email.order.orderStatus' => 'Bestellstatus',
    'email.order.paymentStatus' => 'Zahlungsstatus',
    'email.order.items' => 'Artikel',
    'email.order.product' => 'Produkt',
    'email.order.sku' => 'SKU',
    'email.order.qty' => 'Menge',
    'email.order.price' => 'Preis',
    'email.order.total' => 'Gesamt',
    'email.order.subtotal' => 'Zwischensumme',
    'email.order.shipping' => 'Versand',
    'email.order.grandTotal' => 'Gesamtsumme',
    'email.order.paymentMethod' => 'Zahlungsmethode',
    'email.order.deliveryMethod' => 'Liefermethode',
    'email.order.pickupBranch' => 'Abholort',
    'email.order.shippingAddress' => 'Lieferadresse',
    'email.order.branch' => 'Filiale',
    'email.order.address' => 'Adresse',
    'email.order.street' => 'Straße',
    'email.order.city' => 'Stadt',
    'email.order.country' => 'Land',
    'email.order.footer.thanks' => 'Vielen Dank für Ihren Einkauf bei NicheHome.ch!',
    'email.order.footer.questions' => 'Bei Fragen kontaktieren Sie uns bitte unter support@nichehome.ch',
    'email.footer.auto' => 'Dies ist eine automatische Nachricht. Bitte antworten Sie nicht auf diese E-Mail.',
    
    // Payment methods
    'email.payment.cash' => 'Barzahlung (bei Abholung)',
    'email.payment.twint' => 'TWINT',
    'email.payment.card' => 'Kredit-/Debitkarte',
    'email.payment.paypal' => 'PayPal',
    
    // Delivery methods
    'email.delivery.delivery' => 'Lieferung',
    'email.delivery.pickup' => 'Abholung',
    'email.delivery.free' => 'GRATIS',
    'email.delivery.pickupInBranch' => 'Abholung in Filiale',
    
    // Order statuses
    'email.status.pending' => 'Ausstehend',
    'email.status.paid' => 'Bezahlt',
    'email.status.awaiting_payment' => 'Wartet auf Zahlung',
    'email.status.awaiting_cash_pickup' => 'Wartet auf Barzahlung bei Abholung',
    'email.status.completed' => 'Abgeschlossen',
    'email.status.fulfilled' => 'Erfüllt',
    'email.status.pending_payment' => 'Zahlung ausstehend',
    'email.status.cash_on_pickup' => 'Barzahlung bei Abholung',
    
    // Support auto-reply email
    'email.support.subject' => 'Wir haben Ihre Anfrage erhalten — NicheHome.ch',
    'email.support.title' => 'Wir haben Ihre Anfrage erhalten',
    'email.support.greeting' => 'Sehr geehrte/r {name},',
    'email.support.intro' => 'Vielen Dank, dass Sie den NicheHome.ch Support kontaktiert haben. Wir haben Ihre Anfrage erhalten und werden so schnell wie möglich antworten.',
    'email.support.yourRequest' => 'Ihre Anfrage',
    'email.support.subject_label' => 'Betreff',
    'email.support.message_label' => 'Nachricht',
    'email.support.responseTime' => 'Unser Support-Team antwortet in der Regel innerhalb von 24-48 Stunden während der Geschäftszeiten.',
    'email.support.footer.thanks' => 'Vielen Dank, dass Sie NicheHome.ch gewählt haben',
    'email.support.footer.urgent' => 'Für dringende Angelegenheiten rufen Sie uns bitte direkt an',
    
    // Admin notification (kept in English)
    'email.admin.order.subject' => 'New Order #{order_id} — NicheHome.ch',
    'email.admin.order.title' => 'New Order Received',
    'email.admin.support.subject' => 'New Support Request — {name}',
    'email.admin.support.title' => 'New Support Request',
];
