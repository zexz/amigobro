<?php
namespace AssistantRouter\Router;

class RouteMap
{
    public static function handlerFor(Intent $intent): string
    {
        return match ($intent) {
            Intent::PRODUCT_SEARCH => 'App\\Application\\Handlers\\ProductSearchHandler',
            Intent::AVAILABILITY   => 'App\\Application\\Handlers\\AvailabilityHandler',
            Intent::ORDER_CREATE   => 'App\\Application\\Handlers\\OrderCreateHandler',
            Intent::DELIVERY_INFO  => 'App\\Application\\Handlers\\DeliveryInfoHandler',
            Intent::PAYMENT_INFO   => 'App\\Application\\Handlers\\PaymentInfoHandler',
            Intent::ORDER_STATUS   => 'App\\Application\\Handlers\\OrderStatusHandler',
            Intent::HUMAN_HANDOFF  => 'App\\Application\\Handlers\\HumanHandoffHandler',
            Intent::FAQ            => 'App\\Application\\Handlers\\FaqHandler',
            Intent::SMALLTALK      => 'App\\Application\\Handlers\\SmalltalkHandler',
            default                => 'App\\Application\\Handlers\\UnknownHandler',
        };
    }
}
