<?php

return [
    'notification_rules' => 'Notification Rules',
    'notification_master' => 'Notification Master',
    'create_rule' => 'Create Notification Rule',
    'new_rule' => 'New Notification Rule',
    'edit_rule' => 'Edit Notification Rule',
    'back_to_rules' => 'Back to Rules',
    'platform_automation' => 'Platform / Automation / Notification Rules',
    'platform_create' => 'Platform / Notification Rules / Create',
    'platform_edit' => 'Platform / Notification Rules / Edit',

    // KPI & Summary
    'total_configured_rules' => 'Total Configured Rules',
    'active_rules' => 'Active Rules',
    'available_erp_events' => 'Available ERP Events',
    'all_modules' => 'All Modules',
    'search_rules' => 'Search rules...',

    // Table Headers
    'rule_name_event' => 'Rule Name & Trigger Event',
    'module' => 'Module',
    'recipients' => 'Recipients (Roles / Users)',
    'bell_message_preview' => 'Header Bell Message Preview',
    'status' => 'Status',
    'action' => 'Action',
    'no_rules_found' => 'No Custom Notification Rules Found',
    'no_rules_help' => 'Create rules to determine which roles & users receive In-App Header Bell notifications.',
    'create_first_rule' => 'Create First Rule',
    'test_send_tooltip' => 'Test Send to Header Bell',
    'edit_tooltip' => 'Edit Rule',
    'delete_tooltip' => 'Delete Rule',
    'confirm_delete' => 'Are you sure you want to delete this notification rule?',
    'creator' => 'Creator',
    'assigned_user' => 'Assigned User',
    'users_count' => '+:count User(s)',
    'none' => '— None —',

    // Section 1: Trigger Event
    'sec_1_title' => '1. Select Trigger Event',
    'sec_1_edit_title' => '1. Trigger Event Details',
    'sec_1_subtitle' => 'Choose which ERP workflow triggers the header bell notification',
    'sec_1_edit_subtitle' => 'Target ERP action and associated triggering workflow',
    'event_trigger' => 'ERP Event Trigger',
    'choose_event' => '-- Choose an Event --',
    'event_helper' => 'Select which ERP action triggers this notification.',
    'rule_name' => 'Rule Name',
    'rule_name_placeholder' => 'e.g. Low Stock Alert for Store Team',

    // Section 2: Recipients
    'sec_2_title' => '2. Who Should Receive Header Bell Notification?',
    'sec_2_subtitle' => 'Target roles and specific users who will receive the in-app bell alert',
    'target_roles' => 'Target Roles',
    'target_roles_placeholder' => 'Select Target Roles...',
    'target_roles_helper' => 'All employees assigned to any of the selected roles will receive this notification in their header bell.',
    'specific_users' => 'Specific Users',
    'specific_users_placeholder' => 'Select individual users (optional)...',
    'specific_users_helper' => 'Directly notify specific team members in addition to their roles.',
    'notify_creator' => 'Notify Record Creator',
    'notify_creator_desc' => 'Send notification to the user who performed/created the action.',
    'notify_assigned_user' => 'Notify Assigned Executive',
    'notify_assigned_user_desc' => 'Send to the assigned sales rep, manager, or operator.',

    // Section 3: Message Template
    'sec_3_title' => '3. Header Bell Message Template',
    'sec_3_subtitle' => 'Define title and message content using dynamic event parameters',
    'dynamic_placeholders' => 'Click to Insert Dynamic Placeholders:',
    'notification_title' => 'Notification Title',
    'title_placeholder' => 'e.g. New Sales Order Created: {doc_no}',
    'notification_body' => 'Notification Message Body',
    'body_placeholder' => 'e.g. Order {doc_no} has been created by {customer_name} for amount {amount}',
    'action_route' => 'Action Click Route',
    'action_route_placeholder' => 'e.g. inventory.products.index',
    'icon_class' => 'Icon Class',
    'icon_placeholder' => 'e.g. feather-bell',

    // Section 4: Actions
    'enable_rule' => 'Enable this Rule Immediately',
    'rule_is_active' => 'Rule is Active',
    'cancel' => 'Cancel',
    'save_rule' => 'Save Notification Rule',
    'update_rule' => 'Update Notification Rule',

    // Live Preview
    'live_preview_title' => 'Live Bell Preview',
    'realtime' => 'Real-time',
    'live_preview_desc' => 'Live mockup of how this notification will appear in the top header bell dropdown:',
    'just_now' => 'Just now',
    'delivery_behavior' => 'Delivery Behavior:',
    'delivery_desc' => 'Users with matching roles will instantly see a red unread counter on the top bell icon upon trigger execution.',

    // Flash Messages
    'rule_created' => 'Notification rule created successfully!',
    'rule_updated' => 'Notification rule updated successfully!',
    'rule_deleted' => 'Notification rule deleted successfully!',
    'rule_toggled' => 'Rule status updated successfully.',
    'test_sent' => 'Test notification sent to your bell successfully!',
];
