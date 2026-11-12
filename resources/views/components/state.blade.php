@props(['value'])
<x-badge :tone="in_array($value, ['active', 'location_validated', 'completed', 'approved', 'resolved']) ? 'teal' : (in_array($value, ['pending_activation', 'pending_review', 'pending', 'exception_approved']) ? 'blue' : 'neutral')">{{ str_replace('_', ' ', ucfirst($value)) }}</x-badge>
