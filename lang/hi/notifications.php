<?php

return [
    'notification_rules' => 'अधिसूचना नियम (Notification Rules)',
    'notification_master' => 'अधिसूचना मास्टर (Notification Master)',
    'create_rule' => 'नया अधिसूचना नियम बनाएं',
    'new_rule' => 'नया अधिसूचना नियम',
    'edit_rule' => 'अधिसूचना नियम संपादित करें',
    'back_to_rules' => 'नियमों पर वापस जाएं',
    'platform_automation' => 'प्लेटफ़ॉर्म / ऑटोमेशन / अधिसूचना नियम',
    'platform_create' => 'प्लेटफ़ॉर्म / अधिसूचना नियम / बनाएं',
    'platform_edit' => 'प्लेटफ़ॉर्म / अधिसूचना नियम / संपादन',

    // KPI & Summary
    'total_configured_rules' => 'कुल कॉन्फ़िगर नियम',
    'active_rules' => 'सक्रिय नियम',
    'available_erp_events' => 'उपलब्ध ईआरपी इवेंट्स',
    'all_modules' => 'सभी मॉड्यूल',
    'search_rules' => 'नियम खोजें...',

    // Table Headers
    'rule_name_event' => 'नियम का नाम और ट्रिगर इवेंट',
    'module' => 'मॉड्यूल',
    'recipients' => 'प्राप्तकर्ता (रोल्स / यूज़र्स)',
    'bell_message_preview' => 'हेडर बेल संदेश पूर्वावलोकन',
    'status' => 'स्थिति',
    'action' => 'कार्रवाई',
    'no_rules_found' => 'कोई कस्टम अधिसूचना नियम नहीं मिला',
    'no_rules_help' => 'यह निर्धारित करने के लिए नियम बनाएं कि किन भूमिकाओं और उपयोगकर्ताओं को इन-ऐप हेडर बेल अलर्ट प्राप्त होंगे।',
    'create_first_rule' => 'पहला नियम बनाएं',
    'test_send_tooltip' => 'हेडर बेल पर टेस्ट भेजें',
    'edit_tooltip' => 'नियम संपादित करें',
    'delete_tooltip' => 'नियम हटाएं',
    'confirm_delete' => 'क्या आप वाकई इस अधिसूचना नियम को हटाना चाहते हैं?',
    'creator' => 'निर्माता (Creator)',
    'assigned_user' => 'असाइन किया गया यूज़र',
    'users_count' => '+:count उपयोगकर्ता',
    'none' => '— कोई नहीं —',

    // Section 1: Trigger Event
    'sec_1_title' => '1. ट्रिगर इवेंट चुनें',
    'sec_1_edit_title' => '1. ट्रिगर इवेंट विवरण',
    'sec_1_subtitle' => 'चुनें कि कौन सा ईआरपी वर्कफ़्लो हेडर बेल अधिसूचना ट्रिगर करेगा',
    'sec_1_edit_subtitle' => 'लक्षित ईआरपी कार्रवाई और संबद्ध ट्रिगर वर्कफ़्लो',
    'event_trigger' => 'ईआरपी इवेंट ट्रिगर',
    'choose_event' => '-- एक इवेंट चुनें --',
    'event_helper' => 'चुनें कि कौन सी ईआरपी कार्रवाई इस अधिसूचना को ट्रिगर करती है।',
    'rule_name' => 'नियम का नाम',
    'rule_name_placeholder' => 'उदा. स्टोर टीम के लिए लो स्टॉक अलर्ट',

    // Section 2: Recipients
    'sec_2_title' => '2. हेडर बेल अधिसूचना किसे प्राप्त होनी चाहिए?',
    'sec_2_subtitle' => 'लक्षित भूमिकाएं और विशिष्ट उपयोगकर्ता जिन्हें इन-ऐप बेल अलर्ट मिलेगा',
    'target_roles' => 'लक्षित भूमिकाएं (Target Roles)',
    'target_roles_placeholder' => 'लक्षित भूमिकाएं चुनें...',
    'target_roles_helper' => 'चयनित भूमिकाओं में से किसी को भी सौंपे गए सभी कर्मचारियों को उनके हेडर बेल में यह अधिसूचना प्राप्त होगी।',
    'specific_users' => 'विशिष्ट उपयोगकर्ता (वैकल्पिक)',
    'specific_users_placeholder' => 'व्यक्तिगत उपयोगकर्ता चुनें (वैकल्पिक)...',
    'specific_users_helper' => 'उनकी भूमिकाओं के अतिरिक्त विशिष्ट टीम सदस्यों को सीधे सूचित करें।',
    'notify_creator' => 'रिकॉर्ड निर्माता को सूचित करें',
    'notify_creator_desc' => 'उस उपयोगकर्ता को अधिसूचना भेजें जिसने यह कार्रवाई/रिकॉर्ड बनाया है।',
    'notify_assigned_user' => 'असाइन किए गए कार्यकारी को सूचित करें',
    'notify_assigned_user_desc' => 'असाइन किए गए सेल्स प्रतिनिधि, प्रबंधक या ऑपरेटर को भेजें।',

    // Section 3: Message Template
    'sec_3_title' => '3. हेडर बेल संदेश टेम्पलेट',
    'sec_3_subtitle' => 'डायनामिक इवेंट पैरामीटर का उपयोग करके शीर्षक और संदेश सामग्री परिभाषित करें',
    'dynamic_placeholders' => 'डायनामिक प्लेसहोल्डर सम्मिलित करने के लिए क्लिक करें:',
    'notification_title' => 'अधिसूचना शीर्षक (Notification Title)',
    'title_placeholder' => 'उदा. नया बिक्री आदेश बनाया गया: {doc_no}',
    'notification_body' => 'अधिसूचना संदेश (Notification Message Body)',
    'body_placeholder' => 'उदा. ग्राहक {customer_name} द्वारा राशि {amount} के लिए आदेश {doc_no} बनाया गया है',
    'action_route' => 'क्लिक करने पर खुलने वाला रूट (Action Route)',
    'action_route_placeholder' => 'उदा. inventory.products.index',
    'icon_class' => 'आइकन क्लास (Icon Class)',
    'icon_placeholder' => 'उदा. feather-bell',

    // Section 4: Actions
    'enable_rule' => 'इस नियम को तुरंत सक्षम (Enable) करें',
    'rule_is_active' => 'नियम सक्रिय है',
    'cancel' => 'रद्द करें',
    'save_rule' => 'अधिसूचना नियम सहेजें',
    'update_rule' => 'अधिसूचना नियम अपडेट करें',

    // Live Preview
    'live_preview_title' => 'लाइव बेल पूर्वावलोकन',
    'realtime' => 'रियल-टाइम',
    'live_preview_desc' => 'शीर्ष हेडर बेल ड्रॉपडाउन में यह अधिसूचना कैसी दिखाई देगी, इसका लाइव मॉकअप:',
    'just_now' => 'अभी-अभी',
    'delivery_behavior' => 'वितरण व्यवहार (Delivery):',
    'delivery_desc' => 'ट्रिगर निष्पादन पर मेल खाने वाली भूमिकाओं वाले उपयोगकर्ताओं को तुरंत शीर्ष बेल आइकन पर एक लाल अपठित काउंटर दिखाई देगा।',

    // Flash Messages
    'rule_created' => 'अधिसूचना नियम सफलतापूर्वक बनाया गया!',
    'rule_updated' => 'अधिसूचना नियम सफलतापूर्वक अपडेट किया गया!',
    'rule_deleted' => 'अधिसूचना नियम सफलतापूर्वक हटा दिया गया!',
    'rule_toggled' => 'नियम की स्थिति सफलतापूर्वक अपडेट की गई।',
    'test_sent' => 'आपकी बेल पर टेस्ट अधिसूचना सफलतापूर्वक भेजी गई!',
];
