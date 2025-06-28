<?php
// 配置信息
$config = [
    'admin_password' => 'admin123', // 管理员密码，建议修改
    'upload_dir' => 'albums',       // 相册存储目录
];

// 初始化目录
if (!file_exists($config['upload_dir'])) {
    mkdir($config['upload_dir'], 0777, true);
}

// 会话管理
session_start();
$is_admin = isset($_SESSION['admin']) && $_SESSION['admin'] === true;

// 处理管理员登录
if (isset($_POST['login']) && $_POST['password'] === $config['admin_password']) {
    $_SESSION['admin'] = true;
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// 处理管理员注销
if (isset($_GET['logout'])) {
    unset($_SESSION['admin']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// 获取当前目录路径
$current_dir = $config['upload_dir'];
if (isset($_GET['dir'])) {
    $requested_dir = $config['upload_dir'] . '/' . trim($_GET['dir'], '/');
    if (is_dir($requested_dir) && strpos(realpath($requested_dir), realpath($config['upload_dir'])) === 0) {
        $current_dir = $requested_dir;
    }
}

// 处理相册密码验证
$album_password_file = $current_dir . '/.password';
$album_is_protected = file_exists($album_password_file);
$album_access_granted = false;

if ($album_is_protected) {
    if (isset($_SESSION['album_access'][$current_dir]) && $_SESSION['album_access'][$current_dir]) {
        $album_access_granted = true;
    } elseif (isset($_POST['album_password'])) {
        $stored_password = trim(file_get_contents($album_password_file));
        if (password_verify($_POST['album_password'], $stored_password)) {
            $_SESSION['album_access'][$current_dir] = true;
            $album_access_granted = true;
            header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
            exit;
        } else {
            $message = '密码错误';
        }
    }
} else {
    $album_access_granted = true; // 未加密的相册默认允许访问
}

// 处理文件上传（仅管理员）
if ($is_admin && isset($_POST['upload']) && !empty($_FILES['image']['name'][0])) {
    $uploaded_count = 0;
    $failed_count = 0;
    
    // 处理多个上传文件
    for ($i = 0; $i < count($_FILES['image']['name']); $i++) {
        $file_name = $_FILES['image']['name'][$i];
        $file_tmp = $_FILES['image']['tmp_name'][$i];
        $file_error = $_FILES['image']['error'][$i];
        
        if ($file_error === UPLOAD_ERR_OK) {
            $upload_path = $current_dir . '/' . basename($file_name);
            
            // 检查文件类型（可选）
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_info = @getimagesize($file_tmp);
            
            if ($file_info && in_array($file_info['mime'], $allowed_types)) {
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    $uploaded_count++;
                } else {
                    $failed_count++;
                }
            } else {
                $failed_count++;
            }
        } else {
            $failed_count++;
        }
    }
    
    if ($uploaded_count > 0) {
        $message = "成功上传 {$uploaded_count} 个文件";
        if ($failed_count > 0) {
            $message .= "，{$failed_count} 个文件上传失败";
        }
    } else {
        $message = "所有文件上传失败";
    }
}

// 处理新建目录（仅管理员）
if ($is_admin && isset($_POST['new_dir'])) {
    $new_dir = $current_dir . '/' . trim($_POST['new_dir_name']);
    if (!file_exists($new_dir) && mkdir($new_dir, 0777, true)) {
        $message = '目录创建成功';
    } else {
        $message = '目录创建失败';
    }
}

// 处理设置相册密码（仅管理员）
if ($is_admin && isset($_POST['set_album_password'])) {
    $password = trim($_POST['album_password']);
    $password_file = $current_dir . '/.password';
    
    if (empty($password)) {
        if (file_exists($password_file)) {
            unlink($password_file);
        }
        $message = '相册密码已移除';
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        file_put_contents($password_file, $hashed_password);
        $message = '相册密码已设置';
    }
}

// 处理重命名目录/文件（仅管理员）
if ($is_admin && isset($_POST['rename'])) {
    $old_name = $current_dir . '/' . $_POST['old_name'];
    $new_name = $current_dir . '/' . $_POST['new_name'];
    if (rename($old_name, $new_name)) {
        $message = '重命名成功';
    } else {
        $message = '重命名失败';
    }
}

// 处理删除目录/文件（仅管理员）
if ($is_admin && isset($_POST['delete'])) {
    $path = $current_dir . '/' . $_POST['delete_path'];
    if (is_file($path)) {
        unlink($path);
        $message = '文件删除成功';
    } elseif (is_dir($path)) {
        deleteDirectory($path);
        $message = '目录删除成功';
    }
}

// 处理移动目录/文件（仅管理员）
if ($is_admin && isset($_POST['move'])) {
    $source = $current_dir . '/' . $_POST['move_source'];
    $target_dir = $config['upload_dir'] . '/' . trim($_POST['move_target'], '/');
    
    if (is_dir($target_dir) && strpos(realpath($target_dir), realpath($config['upload_dir'])) === 0) {
        $target = $target_dir . '/' . basename($source);
        if (rename($source, $target)) {
            $message = '移动成功';
        } else {
            $message = '移动失败';
        }
    } else {
        $message = '目标目录不存在或无效';
    }
}

// 辅助函数：递归删除目录
function deleteDirectory($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir) || is_link($dir)) return unlink($dir);
    
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!deleteDirectory($dir . '/' . $item)) {
            chmod($dir . '/' . $item, 0777);
            if (!deleteDirectory($dir . '/' . $item)) return false;
        }
    }
    
    return rmdir($dir);
}

// 辅助函数：获取目录预览图
function getDirectoryPreview($dir_path) {
    $preview_file = $dir_path . '/.preview';
    if (file_exists($preview_file)) {
        $preview_filename = trim(file_get_contents($preview_file));
        $preview_image = $dir_path . '/' . $preview_filename;
        if (file_exists($preview_image)) {
            return $preview_image;
        }
    }
    
    // 如果没有设置预览图，尝试返回目录中的第一张图片
    $dir_contents = scandir($dir_path);
    $image_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    foreach ($dir_contents as $item) {
        if ($item == '.' || $item == '..') continue;
        
        $item_path = $dir_path . '/' . $item;
        if (is_file($item_path)) {
            $file_info = @getimagesize($item_path);
            if ($file_info && in_array($file_info['mime'], $image_types)) {
                return $item_path;
            }
        }
    }
    
    return null;
}

// 辅助函数：获取文件详细信息
function getFileDetails($file_path) {
    $details = [
        'size' => '',
        'dimensions' => ''
    ];
    
    // 获取文件大小
    if (file_exists($file_path)) {
        $size = filesize($file_path);
        $units = ['B', 'KB', 'MB', 'GB'];
        $unit_index = 0;
        
        while ($size >= 1024 && $unit_index < count($units) - 1) {
            $size /= 1024;
            $unit_index++;
        }
        
        $details['size'] = round($size, 2) . ' ' . $units[$unit_index];
    }
    
    // 获取图片尺寸
    $image_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $file_info = @getimagesize($file_path);
    
    if ($file_info && in_array($file_info['mime'], $image_types)) {
        $details['dimensions'] = $file_info[0] . ' × ' . $file_info[1] . ' px';
    }
    
    return $details;
}

// 获取当前目录内容
$dirs = [];
$files = [];
$current_dir_contents = scandir($current_dir);

foreach ($current_dir_contents as $item) {
    if ($item == '.' || $item == '..' || $item == '.password' || $item == '.preview') continue;
    
    $item_path = $current_dir . '/' . $item;
    if (is_dir($item_path)) {
        $dirs[] = $item;
    } elseif (is_file($item_path)) {
        $files[] = $item;
    }
}

// 获取所有目录列表（用于移动操作）
function getAllDirectories($root_dir) {
    $dirs = [];
    $stack = [$root_dir];
    
    while (!empty($stack)) {
        $current = array_pop($stack);
        $dir_contents = scandir($current);
        
        foreach ($dir_contents as $item) {
            if ($item == '.' || $item == '..') continue;
            
            $item_path = $current . '/' . $item;
            if (is_dir($item_path)) {
                $relative_path = substr($item_path, strlen($root_dir) + 1);
                $dirs[] = $relative_path;
                array_push($stack, $item_path);
            }
        }
    }
    
    return $dirs;
}

$all_dirs = getAllDirectories($config['upload_dir']);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>相册管理系统</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
    
    <!-- Tailwind配置 -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',      // 蓝色主色调
                        secondary: '#10B981',    // 绿色辅助色
                        danger: '#EF4444',       // 红色危险色
                        neutral: {
                            100: '#F3F4F6',
                            200: '#E5E7EB',
                            300: '#D1D5DB',
                            400: '#9CA3AF',
                            500: '#6B7280',
                            600: '#4B5563',
                            700: '#374151',
                            800: '#1F2937',
                            900: '#111827'
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                    boxShadow: {
                        'card': '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
                        'card-hover': '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)',
                    }
                }
            }
        }
    </script>
    
    <style type="text/tailwindcss">
        @layer utilities {
            .content-auto {
                content-visibility: auto;
            }
            .album-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 1.5rem;
            }
            .card-transition {
                transition: all 0.3s ease;
            }
            .scale-hover {
                transition: transform 0.2s ease-in-out;
            }
            .scale-hover:hover {
                transform: scale(1.03);
            }
            .backdrop-blur {
                backdrop-filter: blur(8px);
            }
        }
    </style>
</head>
<body class="bg-neutral-100 min-h-screen text-neutral-800 font-sans">
    <div class="container mx-auto px-4 py-6 max-w-7xl">
        <!-- 顶部导航 -->
        <header class="mb-8">
            <div class="bg-white rounded-xl shadow-md p-4 flex justify-between items-center">
                <h1 class="text-2xl font-bold text-primary">
                    <i class="fa fa-image mr-2"></i>相册管理系统
                </h1>
                
                <?php if ($is_admin): ?>
                    <a href="?logout=1" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors flex items-center">
                        <i class="fa fa-sign-out mr-2"></i>退出登录
                    </a>
                <?php else: ?>
                    <a href="?login=1" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors flex items-center">
                        <i class="fa fa-lock mr-2"></i>管理员登录
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- 面包屑导航 -->
            <div class="mt-4 flex items-center text-sm text-neutral-600">
                <a href="?" class="text-primary hover:underline flex items-center">
                    <i class="fa fa-home mr-1"></i> 首页
                </a>
                <?php
                $current_path = '';
                $path_parts = explode('/', substr($current_dir, strlen($config['upload_dir']) + 1));
                
                foreach ($path_parts as $part) {
                    if (empty($part)) continue;
                    $current_path .= '/' . $part;
                    echo '<span class="mx-2 text-neutral-400">/</span>';
                    echo '<a href="?dir=' . urlencode(ltrim($current_path, '/')) . '" class="text-primary hover:underline">' . htmlspecialchars($part) . '</a>';
                }
                ?>
            </div>
        </header>
        
        <!-- 消息提示 -->
        <?php if (isset($message)): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo (strpos($message, '成功') !== false) ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'; ?>">
                <div class="flex items-center">
                    <i class="fa fa-<?php echo (strpos($message, '成功') !== false) ? 'check-circle' : 'exclamation-circle'; ?> mr-3 text-xl"></i>
                    <p><?php echo $message; ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- 登录表单（仅当用户点击登录链接时显示） -->
        <?php if (isset($_GET['login']) && !$is_admin): ?>
            <div class="max-w-md mx-auto">
                <div class="bg-white rounded-xl shadow-lg p-6 border border-neutral-200">
                    <h2 class="text-xl font-bold mb-4 text-center">管理员登录</h2>
                    <form method="post" class="space-y-4">
                        <div>
                            <label for="password" class="block text-sm font-medium text-neutral-700 mb-1">密码:</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-neutral-500">
                                    <i class="fa fa-lock"></i>
                                </span>
                                <input type="password" id="password" name="password" required 
                                    class="w-full pl-10 pr-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                                    placeholder="请输入管理员密码">
                            </div>
                        </div>
                        <div>
                            <button type="submit" name="login" 
                                class="w-full bg-primary hover:bg-primary/90 text-white font-medium py-2 px-4 rounded-lg transition-colors flex items-center justify-center">
                                <i class="fa fa-sign-in mr-2"></i> 登录
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- 相册密码表单（如果需要） -->
            <?php if ($album_is_protected && !$album_access_granted): ?>
                <div class="max-w-md mx-auto">
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-neutral-200">
                        <h2 class="text-xl font-bold mb-4 text-center">需要密码</h2>
                        <p class="text-neutral-600 mb-4 text-center">此相册受密码保护，请输入密码访问。</p>
                        <form method="post" class="space-y-4">
                            <div>
                                <label for="album_password" class="block text-sm font-medium text-neutral-700 mb-1">相册密码:</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-neutral-500">
                                        <i class="fa fa-key"></i>
                                    </span>
                                    <input type="password" id="album_password" name="album_password" required 
                                        class="w-full pl-10 pr-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                                        placeholder="请输入相册密码">
                                </div>
                            </div>
                            <div>
                                <button type="submit" 
                                    class="w-full bg-primary hover:bg-primary/90 text-white font-medium py-2 px-4 rounded-lg transition-colors flex items-center justify-center">
                                    <i class="fa fa-unlock-alt mr-2"></i> 解锁
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- 管理面板（仅对管理员可见） -->
                <?php if ($is_admin): ?>
                    <div class="bg-white rounded-xl shadow-md p-6 mb-8 border border-neutral-200">
                        <h3 class="text-lg font-bold mb-4 flex items-center">
                            <i class="fa fa-cog mr-2 text-primary"></i> 管理操作
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- 文件上传 -->
                            <div class="border border-neutral-200 rounded-lg p-4 hover:border-primary transition-colors">
                                <h4 class="font-medium mb-3 flex items-center text-primary">
                                    <i class="fa fa-upload mr-2"></i> 上传图片
                                </h4>
                                <form method="post" enctype="multipart/form-data" id="uploadForm">
                                    <div class="mb-3">
                                        <label for="image" class="block text-sm text-neutral-600 mb-1">选择图片 (可多选):</label>
                                        <div class="relative">
                                            <div class="flex items-center justify-center w-full">
                                                <label for="image" class="flex flex-col items-center justify-center w-full h-32 border-2 border-neutral-300 border-dashed rounded-lg cursor-pointer bg-neutral-50 hover:bg-neutral-100 transition-colors">
                                                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                                        <i class="fa fa-cloud-upload text-2xl text-neutral-400 mb-2"></i>
                                                        <p class="mb-2 text-sm text-neutral-500"><span class="font-semibold">点击上传文件</span> 或拖放</p>
                                                        <p class="text-xs text-neutral-400">支持 JPG, PNG, GIF, WebP</p>
                                                    </div>
                                                    <input id="image" name="image[]" type="file" class="hidden" multiple accept="image/*" required />
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="upload-preview flex flex-wrap gap-2 mb-3 min-h-[50px]"></div>
                                    <button type="submit" name="upload" class="w-full bg-primary hover:bg-primary/90 text-white font-medium py-2 px-4 rounded-lg transition-colors flex items-center justify-center">
                                        <i class="fa fa-check mr-2"></i> 确认上传
                                    </button>
                                </form>
                            </div>
                            
                            <!-- 新建目录 -->
                            <div class="border border-neutral-200 rounded-lg p-4 hover:border-primary transition-colors">
                                <h4 class="font-medium mb-3 flex items-center text-primary">
                                    <i class="fa fa-folder mr-2"></i> 新建目录
                                </h4>
                                <form method="post">
                                    <div class="mb-3">
                                        <label for="new_dir_name" class="block text-sm text-neutral-600 mb-1">目录名称:</label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-neutral-500">
                                                <i class="fa fa-folder-o"></i>
                                            </span>
                                            <input type="text" id="new_dir_name" name="new_dir_name" required 
                                                class="w-full pl-10 pr-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                                                placeholder="请输入目录名称">
                                        </div>
                                    </div>
                                    <button type="submit" name="new_dir" class="w-full bg-primary hover:bg-primary/90 text-white font-medium py-2 px-4 rounded-lg transition-colors flex items-center justify-center">
                                        <i class="fa fa-plus mr-2"></i> 创建目录
                                    </button>
                                </form>
                            </div>
                            
                            <!-- 设置相册密码 -->
                            <div class="border border-neutral-200 rounded-lg p-4 hover:border-primary transition-colors">
                                <h4 class="font-medium mb-3 flex items-center text-primary">
                                    <i class="fa fa-lock mr-2"></i> 相册密码设置
                                </h4>
                                <form method="post">
                                    <div class="mb-3">
                                        <label for="album_password" class="block text-sm text-neutral-600 mb-1">密码 (留空则移除密码):</label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-neutral-500">
                                                <i class="fa fa-key"></i>
                                            </span>
                                            <input type="password" id="album_password" name="album_password" 
                                                class="w-full pl-10 pr-3 py-2 border border-neutral-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                                                placeholder="请输入密码（留空移除）">
                                        </div>
                                    </div>
                                    <button type="submit" name="set_album_password" class="w-full bg-primary hover:bg-primary/90 text-white font-medium py-2 px-4 rounded-lg transition-colors flex items-center justify-center">
                                        <i class="fa fa-save mr-2"></i> 保存设置
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- 相册内容 -->
                <div class="album-grid">
                    <!-- 显示子目录 -->
                    <?php foreach ($dirs as $dir): 
                        $dir_path = $current_dir . '/' . $dir;
                        $dir_is_protected = file_exists($dir_path . '/.password');
                        $preview_image = getDirectoryPreview($dir_path);
                    ?>
                        <div class="bg-white rounded-xl shadow-card overflow-hidden card-transition scale-hover">
                            <div class="relative h-48 overflow-hidden">
                                <?php if ($dir_is_protected): ?>
                                    <div class="absolute top-3 right-3 bg-black/50 backdrop-blur text-white px-2 py-1 rounded-full text-xs flex items-center">
                                        <i class="fa fa-lock mr-1"></i> 加密
                                    </div>
                                <?php endif; ?>
                                <a href="?dir=<?php echo urlencode(substr($dir_path, strlen($config['upload_dir']) + 1)); ?>">
                                    <?php if ($preview_image): ?>
                                        <img src="<?php echo htmlspecialchars($preview_image); ?>" alt="<?php echo htmlspecialchars($dir); ?>" 
                                            class="w-full h-full object-cover transition-transform duration-500 hover:scale-105">
                                    <?php else: ?>
                                        <div class="w-full h-full bg-neutral-100 flex items-center justify-center">
                                            <i class="fa fa-folder text-5xl text-neutral-300"></i>
                                        </div>
                                    <?php endif; ?>
                                </a>
                            </div>
                            <div class="p-4">
                                <div class="flex justify-between items-center mb-2">
                                    <h3 class="font-semibold text-lg"><?php echo htmlspecialchars($dir); ?></h3>
                                    <span class="text-xs bg-primary/10 text-primary px-2 py-1 rounded-full">
                                        <i class="fa fa-folder-o mr-1"></i> 目录
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-1">
                                    <a href="?dir=<?php echo urlencode(substr($dir_path, strlen($config['upload_dir']) + 1)); ?>" 
                                        class="text-sm text-primary hover:underline flex items-center">
                                        <i class="fa fa-eye mr-1"></i> 查看
                                    </a>
                                    
                                    <!-- 目录操作表单（仅对管理员可见） -->
                                    <?php if ($is_admin): ?>
                                        <div class="flex items-center gap-1">
                                            <form method="post" class="inline-block">
                                                <input type="hidden" name="old_name" value="<?php echo htmlspecialchars($dir); ?>">
                                                <input type="text" name="new_name" value="<?php echo htmlspecialchars($dir); ?>" size="8" 
                                                    class="text-sm border border-neutral-200 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-primary">
                                                <button type="submit" name="rename" class="text-sm text-primary hover:text-primary/80">
                                                    <i class="fa fa-pencil"></i>
                                                </button>
                                            </form>
                                            
                                            <button onclick="confirmDelete('<?php echo htmlspecialchars($dir); ?>', 'dir')" 
                                                class="text-sm text-danger hover:text-danger/80">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                            
                                            <div class="relative inline-block">
                                                <button onclick="toggleMoveMenu(this)" class="text-sm text-primary hover:text-primary/80">
                                                    <i class="fa fa-arrows"></i>
                                                </button>
                                                <div class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-neutral-200 z-10">
                                                    <form method="post" class="p-2">
                                                        <input type="hidden" name="move_source" value="<?php echo htmlspecialchars($dir); ?>">
                                                        <select name="move_target" class="w-full text-sm border border-neutral-200 rounded px-2 py-1 mb-2">
                                                            <?php foreach ($all_dirs as $d): 
                                                                if ($d == substr($dir_path, strlen($config['upload_dir']) + 1)) continue;
                                                            ?>
                                                                <option value="<?php echo htmlspecialchars($d); ?>"><?php echo htmlspecialchars($d); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="submit" name="move" class="w-full text-xs bg-primary text-white rounded px-2 py-1 hover:bg-primary/90">
                                                            移动
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- 显示图片文件 -->
                    <?php foreach ($files as $file): 
                        $file_path = $current_dir . '/' . $file;
                        
                        // 检查是否为图片文件
                        $is_image = false;
                        $image_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                        $file_info = @getimagesize($file_path);
                        
                        if ($file_info && in_array($file_info['mime'], $image_types)) {
                            $is_image = true;
                        }
                        
                        // 获取文件详细信息
                        $file_details = getFileDetails($file_path);
                    ?>
                        <div class="bg-white rounded-xl shadow-card overflow-hidden card-transition scale-hover">
                            <div class="relative h-48 overflow-hidden">
                                <?php if ($is_image): ?>
                                    <a href="<?php echo htmlspecialchars($file_path); ?>" target="_blank">
                                        <img src="<?php echo htmlspecialchars($file_path); ?>" alt="<?php echo htmlspecialchars($file); ?>" 
                                            class="w-full h-full object-cover transition-transform duration-500 hover:scale-105">
                                    </a>
                                <?php else: ?>
                                    <div class="w-full h-full bg-neutral-100 flex items-center justify-center">
                                        <i class="fa fa-file text-5xl text-neutral-300"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent text-white p-2">
                                    <div class="text-xs truncate">
                                        <?php echo htmlspecialchars($file); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="p-4">
                                <div class="flex justify-between items-center mb-2">
                                    <h3 class="font-semibold text-lg truncate"><?php echo htmlspecialchars(pathinfo($file, PATHINFO_FILENAME)); ?></h3>
                                    <span class="text-xs bg-<?php echo $is_image ? 'blue' : 'gray'; ?>/10 text-<?php echo $is_image ? 'blue' : 'gray'; ?>-600 px-2 py-1 rounded-full">
                                        <i class="fa fa-<?php echo $is_image ? 'image' : 'file-o'; ?> mr-1"></i> 
                                        <?php echo htmlspecialchars(pathinfo($file, PATHINFO_EXTENSION)); ?>
                                    </span>
                                </div>
                                
                                <?php if ($is_image): ?>
                                    <div class="text-xs text-neutral-500 mb-2">
                                        <span class="mr-3"><i class="fa fa-arrows-alt mr-1"></i> <?php echo $file_details['dimensions']; ?></span>
                                        <span><i class="fa fa-file-text-o mr-1"></i> <?php echo $file_details['size']; ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="flex flex-wrap gap-1">
                                    <a href="<?php echo htmlspecialchars($file_path); ?>" target="_blank" 
                                        class="text-sm text-primary hover:underline flex items-center">
                                        <i class="fa fa-eye mr-1"></i> 查看
                                    </a>
                                    
                                    <!-- 文件操作表单（仅对管理员可见） -->
                                    <?php if ($is_admin): ?>
                                        <div class="flex items-center gap-1">
                                            <form method="post" class="inline-block">
                                                <input type="hidden" name="old_name" value="<?php echo htmlspecialchars($file); ?>">
                                                <input type="text" name="new_name" value="<?php echo htmlspecialchars(pathinfo($file, PATHINFO_FILENAME)); ?>" size="8" 
                                                    class="text-sm border border-neutral-200 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-primary">
                                                <input type="hidden" name="new_ext" value="<?php echo htmlspecialchars(pathinfo($file, PATHINFO_EXTENSION)); ?>">
                                                <button type="submit" name="rename" class="text-sm text-primary hover:text-primary/80">
                                                    <i class="fa fa-pencil"></i>
                                                </button>
                                            </form>
                                            
                                            <button onclick="confirmDelete('<?php echo htmlspecialchars($file); ?>', 'file')" 
                                                class="text-sm text-danger hover:text-danger/80">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                            
                                            <div class="relative inline-block">
                                                <button onclick="toggleMoveMenu(this)" class="text-sm text-primary hover:text-primary/80">
                                                    <i class="fa fa-arrows"></i>
                                                </button>
                                                <div class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-neutral-200 z-10">
                                                    <form method="post" class="p-2">
                                                        <input type="hidden" name="move_source" value="<?php echo htmlspecialchars($file); ?>">
                                                        <select name="move_target" class="w-full text-sm border border-neutral-200 rounded px-2 py-1 mb-2">
                                                            <?php foreach ($all_dirs as $d): 
                                                                if ($d == substr($current_dir, strlen($config['upload_dir']) + 1)) continue;
                                                            ?>
                                                                <option value="<?php echo htmlspecialchars($d); ?>"><?php echo htmlspecialchars($d); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="submit" name="move" class="w-full text-xs bg-primary text-white rounded px-2 py-1 hover:bg-primary/90">
                                                            移动
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- 页脚 -->
                <footer class="mt-12 py-4 text-center text-neutral-500 text-sm">
                    <p>© 2025 相册管理系统 | 总目录数: <?php echo count($dirs); ?> | 总文件数: <?php echo count($files); ?></p>
                </footer>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- JavaScript -->
    <script>
        // 上传预览功能
        document.getElementById('image').addEventListener('change', function(e) {
            const previewContainer = document.querySelector('.upload-preview');
            previewContainer.innerHTML = '';
            
            for (let i = 0; i < e.target.files.length; i++) {
                const file = e.target.files[i];
                const reader = new FileReader();
                
                reader.onload = function(event) {
                    const img = document.createElement('img');
                    img.src = event.target.result;
                    img.className = 'w-16 h-16 object-cover rounded border border-neutral-200';
                    img.alt = file.name;
                    previewContainer.appendChild(img);
                }
                
                reader.readAsDataURL(file);
            }
        });
        
        // 删除确认对话框
        function confirmDelete(name, type) {
            if (confirm(`确定要删除 ${type === 'dir' ? '目录' : '文件'} "${name}" 吗？此操作不可撤销。`)) {
                const form = document.createElement('form');
                form.method = 'post';
                form.style.display = 'none';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete';
                input.value = '1';
                
                const pathInput = document.createElement('input');
                pathInput.type = 'hidden';
                pathInput.name = 'delete_path';
                pathInput.value = name;
                
                form.appendChild(input);
                form.appendChild(pathInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // 移动菜单切换
        function toggleMoveMenu(button) {
            const menu = button.nextElementSibling;
            menu.classList.toggle('hidden');
            
            // 点击其他地方关闭菜单
            document.addEventListener('click', function(event) {
                if (!button.contains(event.target) && !menu.contains(event.target)) {
                    menu.classList.add('hidden');
                }
            });
        }
        
        // 页面加载完成后执行
        document.addEventListener('DOMContentLoaded', function() {
            // 为所有重命名表单添加扩展名处理
            document.querySelectorAll('form').forEach(form => {
                if (form.querySelector('input[name="new_ext"]')) {
                    form.addEventListener('submit', function(e) {
                        const nameInput = this.querySelector('input[name="new_name"]');
                        const extInput = this.querySelector('input[name="new_ext"]');
                        
                        // 如果用户删除了扩展名，自动添加
                        if (nameInput.value.indexOf('.') === -1 && extInput.value) {
                            nameInput.value += '.' + extInput.value;
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>    
