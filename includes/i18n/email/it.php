<?php
/**
 * Email translations - Italian (Italiano)
 */

return [
    // Order confirmation email
    'email.order.subject' => 'Conferma ordine #{order_id} — NicheHome.ch',
    'email.order.title' => 'Grazie per il tuo ordine!',
    'email.order.greeting' => 'Gentile {customer_name},',
    'email.order.intro' => 'Abbiamo ricevuto il tuo ordine e lo stiamo elaborando. Grazie per aver acquistato su NicheHome.ch!',
    'email.order.orderDetails' => 'Dettagli ordine',
    'email.order.orderNumber' => 'Numero ordine',
    'email.order.orderDate' => 'Data ordine',
    'email.order.orderStatus' => 'Stato ordine',
    'email.order.paymentStatus' => 'Stato pagamento',
    'email.order.items' => 'Articoli',
    'email.order.product' => 'Prodotto',
    'email.order.sku' => 'SKU',
    'email.order.qty' => 'Qtà',
    'email.order.price' => 'Prezzo',
    'email.order.total' => 'Totale',
    'email.order.subtotal' => 'Subtotale',
    'email.order.shipping' => 'Spedizione',
    'email.order.grandTotal' => 'Totale complessivo',
    'email.order.paymentMethod' => 'Metodo di pagamento',
    'email.order.deliveryMethod' => 'Metodo di consegna',
    'email.order.pickupBranch' => 'Punto di ritiro',
    'email.order.shippingAddress' => 'Indirizzo di spedizione',
    'email.order.branch' => 'Filiale',
    'email.order.address' => 'Indirizzo',
    'email.order.street' => 'Via',
    'email.order.city' => 'Città',
    'email.order.country' => 'Paese',
    'email.order.footer.thanks' => 'Grazie per aver acquistato su NicheHome.ch!',
    'email.order.footer.questions' => 'Per domande, contattaci a support@nichehome.ch',
    'email.footer.auto' => 'Questo è un messaggio automatico. Si prega di non rispondere a questa email.',
    
    // Payment methods
    'email.payment.cash' => 'Contanti (al ritiro)',
    'email.payment.twint' => 'TWINT',
    'email.payment.card' => 'Carta di credito/debito',
    'email.payment.paypal' => 'PayPal',
    
    // Delivery methods
    'email.delivery.delivery' => 'Consegna',
    'email.delivery.pickup' => 'Ritiro',
    'email.delivery.free' => 'GRATUITO',
    'email.delivery.pickupInBranch' => 'Ritiro in filiale',
    
    // Order statuses
    'email.status.pending' => 'In attesa',
    'email.status.paid' => 'Pagato',
    'email.status.awaiting_payment' => 'In attesa di pagamento',
    'email.status.awaiting_cash_pickup' => 'In attesa di pagamento in contanti al ritiro',
    'email.status.completed' => 'Completato',
    'email.status.fulfilled' => 'Evaso',
    'email.status.pending_payment' => 'Pagamento in sospeso',
    'email.status.cash_on_pickup' => 'Contanti al ritiro',
    
    // Support auto-reply email
    'email.support.subject' => 'Abbiamo ricevuto la tua richiesta — NicheHome.ch',
    'email.support.title' => 'Abbiamo ricevuto la tua richiesta',
    'email.support.greeting' => 'Gentile {name},',
    'email.support.intro' => 'Grazie per aver contattato il supporto NicheHome.ch. Abbiamo ricevuto la tua richiesta e risponderemo il prima possibile.',
    'email.support.yourRequest' => 'La tua richiesta',
    'email.support.subject_label' => 'Oggetto',
    'email.support.message_label' => 'Messaggio',
    'email.support.responseTime' => 'Il nostro team di supporto risponde generalmente entro 24-48 ore nei giorni lavorativi.',
    'email.support.footer.thanks' => 'Grazie per aver scelto NicheHome.ch',
    'email.support.footer.urgent' => 'Per questioni urgenti, chiamaci direttamente',
    
    // Admin notification (kept in English)
    'email.admin.order.subject' => 'New Order #{order_id} — NicheHome.ch',
    'email.admin.order.title' => 'New Order Received',
    'email.admin.support.subject' => 'New Support Request — {name}',
    'email.admin.support.title' => 'New Support Request',
];
