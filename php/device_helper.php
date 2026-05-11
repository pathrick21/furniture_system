<?php
function getDeviceInfo() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $device_name = 'Unknown Device';
    $device_type = 'Desktop';
    
    // ============================================
    // DETECT DEVICE NAME (Brand/Model)
    // ============================================
    
    // iPhone Detection
    if (preg_match('/iPhone/i', $user_agent)) {
        $device_type = 'Mobile';
        // Try to get iPhone model (iPhone14,2 = iPhone 13 Pro, etc)
        if (preg_match('/iPhone(\d+,\d+)/i', $user_agent, $matches)) {
            $model_code = $matches[1];
            $device_name = getiPhoneModel($model_code);
        } else {
            $device_name = 'iPhone';
        }
    }
    // iPad Detection
    elseif (preg_match('/iPad/i', $user_agent)) {
        $device_type = 'Tablet';
        $device_name = 'iPad';
        // Try to detect iPad type
        if (preg_match('/iPad.*?OS (\d+(_\d+)*)/', $user_agent, $matches)) {
            $ios_version = str_replace('_', '.', $matches[1]);
            $device_name = "iPad (iOS $ios_version)";
        }
    }
    // Samsung Detection
    elseif (preg_match('/SamsungBrowser|Samsung/i', $user_agent) || preg_match('/SM-[A-Za-z0-9]+/', $user_agent, $matches)) {
        $device_type = 'Mobile';
        if (preg_match('/SM-([A-Za-z0-9]+)/', $user_agent, $matches)) {
            $model = $matches[1];
            $device_name = "Samsung Galaxy $model";
        } else {
            $device_name = 'Samsung Galaxy';
        }
    }
    // Xiaomi/Redmi Detection
    elseif (preg_match('/Xiaomi|Redmi/i', $user_agent)) {
        $device_type = 'Mobile';
        if (preg_match('/([A-Za-z0-9]+)_miui|MiBrowser/i', $user_agent, $matches)) {
            $device_name = "Xiaomi " . $matches[1];
        } elseif (preg_match('/Redmi ([A-Za-z0-9]+)/i', $user_agent, $matches)) {
            $device_name = "Redmi " . $matches[1];
        } else {
            $device_name = preg_match('/Redmi/i', $user_agent) ? 'Redmi' : 'Xiaomi';
        }
    }
    // Huawei Detection
    elseif (preg_match('/HUAWEI|HONOR/i', $user_agent)) {
        $device_type = 'Mobile';
        if (preg_match('/HUAWEI[_\s]?([A-Za-z0-9\-]+)/i', $user_agent, $matches)) {
            $device_name = "Huawei " . $matches[1];
        } elseif (preg_match('/HONOR[_\s]?([A-Za-z0-9\-]+)/i', $user_agent, $matches)) {
            $device_name = "Honor " . $matches[1];
        } else {
            $device_name = preg_match('/HONOR/i', $user_agent) ? 'Honor' : 'Huawei';
        }
    }
    // Oppo Detection
    elseif (preg_match('/OPPO|oppo/i', $user_agent)) {
        $device_type = 'Mobile';
        if (preg_match('/(CPH[0-9]+)/i', $user_agent, $matches)) {
            $device_name = "Oppo " . $matches[1];
        } else {
            $device_name = 'Oppo';
        }
    }
    // Vivo Detection
    elseif (preg_match('/vivo/i', $user_agent)) {
        $device_type = 'Mobile';
        if (preg_match('/(V[0-9]+[A-Za-z]+)/i', $user_agent, $matches)) {
            $device_name = "Vivo " . $matches[1];
        } else {
            $device_name = 'Vivo';
        }
    }
    // Realme Detection
    elseif (preg_match('/realme/i', $user_agent)) {
        $device_type = 'Mobile';
        if (preg_match('/(RMX[0-9]+)/i', $user_agent, $matches)) {
            $device_name = "Realme " . $matches[1];
        } else {
            $device_name = 'Realme';
        }
    }
    // OnePlus Detection
    elseif (preg_match('/OnePlus/i', $user_agent)) {
        $device_type = 'Mobile';
        if (preg_match('/OnePlus\s?([A-Za-z0-9]+)/i', $user_agent, $matches)) {
            $device_name = "OnePlus " . $matches[1];
        } else {
            $device_name = 'OnePlus';
        }
    }
    // Google Pixel Detection
    elseif (preg_match('/Pixel/i', $user_agent)) {
        $device_type = 'Mobile';
        if (preg_match('/Pixel\s?([0-9a-zA-Z]+)/i', $user_agent, $matches)) {
            $device_name = "Google Pixel " . $matches[1];
        } else {
            $device_name = 'Google Pixel';
        }
    }
    // Motorola Detection
    elseif (preg_match('/Moto|Motorola/i', $user_agent)) {
        $device_type = 'Mobile';
        if (preg_match('/Moto\s?([A-Za-z0-9]+)/i', $user_agent, $matches)) {
            $device_name = "Motorola " . $matches[1];
        } else {
            $device_name = 'Motorola';
        }
    }
    // Nokia Detection
    elseif (preg_match('/Nokia/i', $user_agent)) {
        $device_type = 'Mobile';
        if (preg_match('/Nokia\s?([A-Za-z0-9\.]+)/i', $user_agent, $matches)) {
            $device_name = "Nokia " . $matches[1];
        } else {
            $device_name = 'Nokia';
        }
    }
    // Asus Detection
    elseif (preg_match('/ASUS|Asus/i', $user_agent)) {
        $device_type = preg_match('/Pad|Tablet/i', $user_agent) ? 'Tablet' : 'Mobile';
        if (preg_match('/(ZE[0-9]+[A-Z]*|ASUS_[A-Za-z0-9]+)/i', $user_agent, $matches)) {
            $device_name = "Asus " . $matches[1];
        } else {
            $device_name = 'Asus';
        }
    }
    // LG Detection
    elseif (preg_match('/LG[-_\s]?([A-Za-z0-9]+)/i', $user_agent, $matches)) {
        $device_type = 'Mobile';
        $device_name = "LG " . $matches[1];
    }
    // Sony Xperia Detection
    elseif (preg_match('/Sony|Xperia/i', $user_agent)) {
        $device_type = 'Mobile';
        if (preg_match('/(Xperia[_\s]?[A-Za-z0-9]+)/i', $user_agent, $matches)) {
            $device_name = "Sony " . str_replace('_', ' ', $matches[1]);
        } else {
            $device_name = 'Sony Xperia';
        }
    }
    // Lenovo Detection
    elseif (preg_match('/Lenovo/i', $user_agent)) {
        $device_type = preg_match('/Tablet|Pad/i', $user_agent) ? 'Tablet' : 'Mobile';
        $device_name = 'Lenovo';
    }
    // Generic Android Mobile
    elseif (preg_match('/Android.*Mobile/i', $user_agent)) {
        $device_type = 'Mobile';
        $device_name = 'Android Phone';
    }
    // Generic Android Tablet
    elseif (preg_match('/Android/i', $user_agent)) {
        $device_type = 'Tablet';
        $device_name = 'Android Tablet';
    }
    // Windows Phone
    elseif (preg_match('/Windows Phone|IEMobile/i', $user_agent)) {
        $device_type = 'Mobile';
        $device_name = 'Windows Phone';
    }
    // BlackBerry
    elseif (preg_match('/BlackBerry|BB10/i', $user_agent)) {
        $device_type = 'Mobile';
        $device_name = 'BlackBerry';
    }
    // Desktop (no specific device name)
    else {
        $device_type = 'Desktop';
        $device_name = 'Computer';
    }
    
    // ============================================
    // BROWSER DETECTION
    // ============================================
    $browser = 'Unknown Browser';
    if (preg_match('/Edg\/([0-9.]+)/', $user_agent, $matches)) {
        $browser = 'Edge ' . $matches[1];
    } elseif (preg_match('/OPR\/([0-9.]+)/', $user_agent, $matches)) {
        $browser = 'Opera ' . $matches[1];
    } elseif (preg_match('/Chrome\/([0-9.]+)/', $user_agent, $matches) && !preg_match('/Edg|OPR/', $user_agent)) {
        $browser = 'Chrome';
    } elseif (preg_match('/Safari\/([0-9.]+)/', $user_agent, $matches) && !preg_match('/Chrome/', $user_agent)) {
        $browser = 'Safari';
    } elseif (preg_match('/Firefox\/([0-9.]+)/', $user_agent, $matches)) {
        $browser = 'Firefox';
    } elseif (preg_match('/SamsungBrowser\/([0-9.]+)/', $user_agent, $matches)) {
        $browser = 'Samsung Internet';
    } elseif (preg_match('/MiuiBrowser|MiBrowser/i', $user_agent)) {
        $browser = 'Mi Browser';
    } elseif (preg_match('/Trident|MSIE/i', $user_agent)) {
        $browser = 'Internet Explorer';
    }
    
    // ============================================
    // OS DETECTION
    // ============================================
    $os = 'Unknown OS';
    if (preg_match('/Windows NT 10.0/', $user_agent)) {
        $os = 'Windows 11/10';
    } elseif (preg_match('/Windows NT 6.3/', $user_agent)) {
        $os = 'Windows 8.1';
    } elseif (preg_match('/Windows NT 6.2/', $user_agent)) {
        $os = 'Windows 8';
    } elseif (preg_match('/Windows NT 6.1/', $user_agent)) {
        $os = 'Windows 7';
    } elseif (preg_match('/Mac OS X ([0-9_]+)/', $user_agent, $matches)) {
        $os = 'macOS ' . str_replace('_', '.', $matches[1]);
    } elseif (preg_match('/Linux/', $user_agent) && !preg_match('/Android/', $user_agent)) {
        $os = 'Linux';
    } elseif (preg_match('/Android ([0-9.]+)/', $user_agent, $matches)) {
        $os = 'Android ' . $matches[1];
    } elseif (preg_match('/iPhone OS ([0-9_]+)/', $user_agent, $matches)) {
        $os = 'iOS ' . str_replace('_', '.', $matches[1]);
    } elseif (preg_match('/iPad.*?OS ([0-9_]+)/', $user_agent, $matches)) {
        $os = 'iPadOS ' . str_replace('_', '.', $matches[1]);
    }
    
    return [
        'device_name' => $device_name,  // NEW: iPhone, Samsung Galaxy, etc
        'device_type' => $device_type,  // Mobile, Tablet, Desktop
        'device' => $device_type,       // For backward compatibility
        'browser' => $browser,          // Chrome, Safari, etc (without version number)
        'os' => $os,                    // Windows, Android, iOS, etc
        'full_user_agent' => substr($user_agent, 0, 250)
    ];
}

// Helper function to map iPhone model codes
function getiPhoneModel($model_code) {
    $models = [
        '18,1' => 'iPhone 16 Pro Max',
        '18,2' => 'iPhone 16 Pro',
        '18,3' => 'iPhone 16 Plus',
        '18,4' => 'iPhone 16',
        '17,1' => 'iPhone 15 Pro Max',
        '17,2' => 'iPhone 15 Pro',
        '17,3' => 'iPhone 15 Plus',
        '17,4' => 'iPhone 15',
        '16,1' => 'iPhone 14 Pro Max',
        '16,2' => 'iPhone 14 Pro',
        '14,7' => 'iPhone 14',
        '14,8' => 'iPhone 14 Plus',
        '15,2' => 'iPhone 13 Pro',
        '15,3' => 'iPhone 13 Pro Max',
        '15,4' => 'iPhone 13',
        '15,5' => 'iPhone 13 Mini',
        '14,2' => 'iPhone 12 Pro',
        '14,3' => 'iPhone 12 Pro Max',
        '14,4' => 'iPhone 12 Mini',
        '14,5' => 'iPhone 12',
        '13,2' => 'iPhone 11',
        '13,3' => 'iPhone 11 Pro',
        '13,4' => 'iPhone 11 Pro Max',
        '12,1' => 'iPhone XR',
        '12,3' => 'iPhone XS',
        '12,5' => 'iPhone XS Max',
        '11,2' => 'iPhone X',
        '11,4' => 'iPhone 8 Plus',
        '11,6' => 'iPhone 8',
    ];
    
    return $models[$model_code] ?? 'iPhone';
}

function getClientIP() {
    $ip = 'Unknown';
    
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR'
    ];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            foreach ($ips as $ip_candidate) {
                $ip_candidate = trim($ip_candidate);
                if (filter_var($ip_candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip_candidate;
                }
            }
            $ip = trim($ips[0]);
        }
    }
    
    return $ip;
}
?>