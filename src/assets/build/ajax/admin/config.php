<?php 

return [
    'conditions' => [
        'admin_features_page' => function() {
            return is_admin() && isset($_GET['page']) && $_GET['page'] === 'meros_features';
        },
    ]
];