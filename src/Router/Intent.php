<?php
namespace AssistantRouter\Router;

enum Intent: string {
    case PRODUCT_SEARCH = 'product_search';
    case AVAILABILITY   = 'availability_check';
    case ORDER_CREATE   = 'order_create';
    case ORDER_STATUS   = 'order_status';
    case DELIVERY_INFO  = 'delivery_info';
    case PAYMENT_INFO   = 'payment_info';
    case RETURNS_INFO   = 'returns_info';
    case FAQ            = 'faq';
    case HUMAN_HANDOFF  = 'human_handoff';
    case SMALLTALK      = 'smalltalk';
    case UNKNOWN        = 'unknown';
}
