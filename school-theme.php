<?php
// includes/school-theme.php

function getSchoolTheme($school_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT primary_color, secondary_color, accent_color, 
               logo, banner, currency, currency_symbol, school_name 
        FROM schools 
        WHERE id = ?
    ");
    $stmt->execute([$school_id]);
    $school = $stmt->fetch();
    
    if (!$school) {
        return [
            'primary_color' => '#009543',
            'secondary_color' => '#002B7F',
            'accent_color' => '#FBDE4A',
            'logo' => '/assets/images/default-school-logo.png',
            'banner' => '/assets/images/default-school-banner.jpg',
            'currency' => 'FCFA',
            'currency_symbol' => 'FCFA',
            'school_name' => 'École'
        ];
    }
    
    return $school;
}

function generateSchoolCSS($school_id) {
    $theme = getSchoolTheme($school_id);
    $css_file = "assets/css/school-{$school_id}.css";
    
    $css = "
        /* CSS personnalisé pour {$theme['school_name']} */
        
        :root {
            --primary-color: {$theme['primary_color']};
            --secondary-color: {$theme['secondary_color']};
            --accent-color: {$theme['accent_color']};
        }
        
        .school-primary-bg {
            background-color: {$theme['primary_color']} !important;
        }
        
        .school-primary-text {
            color: {$theme['primary_color']} !important;
        }
        
        .school-primary-border {
            border-color: {$theme['primary_color']} !important;
        }
        
        .school-secondary-bg {
            background-color: {$theme['secondary_color']} !important;
        }
        
        .school-secondary-text {
            color: {$theme['secondary_color']} !important;
        }
        
        .school-accent-bg {
            background-color: {$theme['accent_color']} !important;
        }
        
        .school-accent-text {
            color: {$theme['accent_color']} !important;
        }
        
        .btn-school-primary {
            background: {$theme['primary_color']};
            border-color: {$theme['primary_color']};
            color: white;
        }
        
        .btn-school-primary:hover {
            background: " . darkenColor($theme['primary_color'], 20) . ";
            border-color: " . darkenColor($theme['primary_color'], 20) . ";
        }
        
        .navbar-school {
            background: linear-gradient(135deg, {$theme['primary_color']}, {$theme['secondary_color']});
        }
        
        .sidebar-school {
            background: {$theme['secondary_color']};
        }
        
        .card-header-school {
            background: {$theme['primary_color']};
            color: white;
        }
    ";
    
    file_put_contents($css_file, $css);
    return $css_file;
}

function darkenColor($color, $percent) {
    $color = ltrim($color, '#');
    $rgb = [
        hexdec(substr($color, 0, 2)),
        hexdec(substr($color, 2, 2)),
        hexdec(substr($color, 4, 2))
    ];
    
    for ($i = 0; $i < 3; $i++) {
        $rgb[$i] = round($rgb[$i] * (100 - $percent) / 100);
    }
    
    return sprintf("#%02x%02x%02x", $rgb[0], $rgb[1], $rgb[2]);
}

function formatCurrency($amount, $school_id) {
    $theme = getSchoolTheme($school_id);
    $currency = $theme['currency_symbol'] ?? 'FCFA';
    
    return number_format($amount, 0, ',', ' ') . ' ' . $currency;
}

function getSchoolLogo($school_id, $size = 'medium') {
    $theme = getSchoolTheme($school_id);
    $logo = $theme['logo'] ?: '/assets/images/default-school-logo.png';
    
    $sizes = [
        'small' => '50px',
        'medium' => '100px',
        'large' => '150px'
    ];
    
    $width = $sizes[$size] ?? '100px';
    
    return '<img src="' . $logo . '" alt="Logo" style="width: ' . $width . '; height: auto;">';
}
?>