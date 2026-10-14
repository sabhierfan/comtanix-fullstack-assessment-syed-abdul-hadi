<?php

namespace App\Enums;

enum AuditEventType: string
{
    case Created = 'quote_request.created';
    case StatusChanged = 'quote_request.status_changed';
    case Assigned = 'quote_request.assigned';
    case Deleted = 'quote_request.deleted';
}
