<?php

return [
    'modules' => [
        'employees' => ['title' => 'Employee Management', 'description' => 'A central place for your people and their work assignments.', 'action' => 'Add employee', 'empty' => 'Your employee directory starts here', 'detail' => 'Employee registration and management will be available in a later phase.', 'columns' => ['Employee ID', 'Employee name', 'Department', 'Branch', 'Position', 'Employment status', 'Actions']],
        'branches' => ['title' => 'Branch Management', 'description' => 'Bring every office together in one workspace.', 'action' => 'Add branch', 'empty' => 'Your branch directory starts here', 'detail' => 'Branch registration and location configuration will be added in a later phase.', 'columns' => ['Branch code', 'Branch name', 'Address', 'Latitude', 'Longitude', 'Geofence radius', 'Branch status']],
        'leave' => ['title' => 'Leave Management', 'description' => 'Keep time-off requests and decisions organized.', 'action' => 'Apply for leave', 'empty' => 'Leave requests will appear here', 'detail' => 'Applications, approvals, and leave history are not enabled yet.', 'columns' => ['Employee', 'Leave type', 'Dates', 'Status', 'Reviewer', 'Actions']],
        'reports' => ['title' => 'Reports and Analytics', 'description' => 'Turn attendance records into a clearer picture.', 'action' => 'Export report', 'empty' => 'Reports are waiting for real records', 'detail' => 'Report generation and exports will be connected in a later phase.', 'columns' => ['Employee', 'Branch', 'Period', 'Present', 'Late', 'Absent']],
        'notifications' => ['title' => 'Notifications', 'description' => 'Stay up to date with requests, decisions, and account activity.', 'action' => 'Mark all as read', 'empty' => 'A quiet space for your updates', 'detail' => 'Notification delivery is not enabled. No events have been generated.', 'columns' => []],
    ],
];
