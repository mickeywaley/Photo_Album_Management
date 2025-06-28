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
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; max-width: 1200px; margin: 0 auto; }
        .container { margin: 0 auto; }
        .header { background: #f5f5f5; padding: 10px 20px; margin-bottom: 20px; border-radius: 5px; }
        .header h1 { margin: 0; }
        .breadcrumb { margin: 10px 0; }
        .breadcrumb a { color: #007BFF; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
        .gallery-item { border: 1px solid #ddd; border-radius: 5px; overflow: hidden; }
        .gallery-item img { width: 100%; height: 150px; object-fit: cover; }
        .gallery-item .item-info { padding: 10px; }
        .gallery-item .item-name { font-weight: bold; }
        .gallery-item .item-details { font-size: 0.8em; color: #666; margin-top: 3px; }
        .gallery-item .item-actions { margin-top: 5px; font-size: 0.9em; }
        .gallery-item .item-actions a { color: #007BFF; margin-right: 10px; }
        .admin-panel { margin: 20px 0; padding: 15px; background: #f5f5f5; border-radius: 5px; }
        .admin-panel h3 { margin-top: 0; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input[type="text"], .form-group input[type="password"], .form-group select { 
            width: 100%; padding: 8px; box-sizing: border-box; 
        }
        .form-group button { padding: 8px 15px; background: #007BFF; color: white; border: none; border-radius: 3px; cursor: pointer; }
        .form-group button:hover { background: #0056b3; }
        .login-form { max-width: 300px; margin: 50px auto; }
        .message { padding: 10px; margin: 10px 0; border-radius: 3px; }
        .message.success { background: #d4edda; color: #155724; }
        .message.error { background: #f8d7da; color: #721c24; }
        .logout { float: right; }
        .password-form { max-width: 300px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .album-lock { position: absolute; top: 5px; right: 5px; background: rgba(0,0,0,0.5); color: white; padding: 2px 5px; border-radius: 3px; }
        .gallery-item { position: relative; }
        .upload-preview { margin-top: 10px; display: flex; flex-wrap: wrap; gap: 10px; }
        .upload-preview img { max-height: 100px; border: 1px solid #ddd; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <!-- 登录表单（仅当用户点击登录按钮时显示） -->
        <?php if ($is_admin): ?>
            <div class="header">
                <h1>相册管理系统</h1>
                <div class="logout"><a href="?logout=1">退出登录</a></div>
            </div>
        <?php else: ?>
            <div class="header">
                <h1>相册浏览</h1>
                <div class="logout"><a href="?login=1">管理员登录</a></div>
            </div>
        <?php endif; ?>
        
        <!-- 面包屑导航 -->
        <div class="breadcrumb">
            <a href="?">首页</a>
            <?php
            $current_path = '';
            $path_parts = explode('/', substr($current_dir, strlen($config['upload_dir']) + 1));
            
            foreach ($path_parts as $part) {
                if (empty($part)) continue;
                $current_path .= '/' . $part;
                echo ' &raquo; <a href="?dir=' . urlencode(ltrim($current_path, '/')) . '">' . htmlspecialchars($part) . '</a>';
            }
            ?>
        </div>
        
        <!-- 消息提示 -->
        <?php if (isset($message)): ?>
            <div class="message <?php echo (strpos($message, '成功') !== false) ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <!-- 登录表单（仅当用户点击登录链接时显示） -->
        <?php if (isset($_GET['login']) && !$is_admin): ?>
            <div class="login-form">
                <h2>管理员登录</h2>
                <form method="post">
                    <div class="form-group">
                        <label for="password">密码:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="login">登录</button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- 相册密码表单（如果需要） -->
            <?php if ($album_is_protected && !$album_access_granted): ?>
                <div class="password-form">
                    <h3>需要密码</h3>
                    <form method="post">
                        <div class="form-group">
                            <label for="album_password">请输入相册密码:</label>
                            <input type="password" id="album_password" name="album_password" required>
                        </div>
                        <div class="form-group">
                            <button type="submit">提交</button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- 管理面板（仅对管理员可见） -->
                <?php if ($is_admin): ?>
                    <div class="admin-panel">
                        <h3>管理操作</h3>
                        
                        <!-- 文件上传 -->
                        <div class="upload-form">
                            <h4>上传图片</h4>
                            <form method="post" enctype="multipart/form-data" id="uploadForm">
                                <div class="form-group">
                                    <label for="image">选择图片 (可多选):</label>
                                    <input type="file" id="image" name="image[]" multiple accept="image/*" required>
                                </div>
                                <div class="upload-preview" id="uploadPreview"></div>
                                <div class="form-group">
                                    <button type="submit" name="upload">上传</button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- 新建目录 -->
                        <div class="new-dir-form">
                            <h4>新建目录</h4>
                            <form method="post">
                                <div class="form-group">
                                    <label for="new_dir_name">目录名称:</label>
                                    <input type="text" id="new_dir_name" name="new_dir_name" required>
                                </div>
                                <div class="form-group">
                                    <button type="submit" name="new_dir">创建</button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- 设置相册密码 -->
                        <div class="password-setting">
                            <h4>设置相册密码</h4>
                            <form method="post">
                                <div class="form-group">
                                    <label for="album_password">密码 (留空则移除密码):</label>
                                    <input type="password" id="album_password" name="album_password">
                                </div>
                                <div class="form-group">
                                    <button type="submit" name="set_album_password">设置</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- 相册内容 -->
                <div class="gallery">
                    <!-- 显示子目录 -->
                    <?php foreach ($dirs as $dir): 
                        $dir_path = $current_dir . '/' . $dir;
                        $dir_is_protected = file_exists($dir_path . '/.password');
                        $preview_image = getDirectoryPreview($dir_path);
                    ?>
                        <div class="gallery-item">
                            <?php if ($dir_is_protected): ?>
                                <div class="album-lock">🔒</div>
                            <?php endif; ?>
                            <a href="?dir=<?php echo urlencode(substr($dir_path, strlen($config['upload_dir']) + 1)); ?>">
                                <?php if ($preview_image): ?>
                                    <img src="<?php echo htmlspecialchars($preview_image); ?>" alt="<?php echo htmlspecialchars($dir); ?>">
                                <?php else: ?>
                                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 200 150'%3E%3Crect width='100%25' height='100%25' fill='%23f0f0f0'/%3E%3Ctext x='100' y='75' text-anchor='middle' dominant-baseline='middle' font-family='Arial' font-size='16' fill='%23666'%3EDirectory%3C/text%3E%3C/svg%3E" alt="目录图标">
                                <?php endif; ?>
                            </a>
                            <div class="item-info">
                                <div class="item-name"><?php echo htmlspecialchars($dir); ?></div>
                                <div class="item-actions">
                                    <a href="?dir=<?php echo urlencode(substr($dir_path, strlen($config['upload_dir']) + 1)); ?>">查看</a>
                                    
                                    <!-- 目录操作表单（仅对管理员可见） -->
                                    <?php if ($is_admin): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="old_name" value="<?php echo htmlspecialchars($dir); ?>">
                                            <input type="text" name="new_name" value="<?php echo htmlspecialchars($dir); ?>" size="8">
                                            <button type="submit" name="rename" style="padding: 2px 5px;">重命名</button>
                                        </form>
                                        
                                        <form method="post" style="display:inline;" onsubmit="return confirm('确定要删除此目录及其所有内容吗？');">
                                            <input type="hidden" name="delete_path" value="<?php echo htmlspecialchars($dir); ?>">
                                            <button type="submit" name="delete" style="padding: 2px 5px;">删除</button>
                                        </form>
                                        
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="move_source" value="<?php echo htmlspecialchars($dir); ?>">
                                            <select name="move_target" style="padding: 2px;">
                                                <?php foreach ($all_dirs as $d): 
                                                    if ($d == substr($dir_path, strlen($config['upload_dir']) + 1)) continue;
                                                ?>
                                                    <option value="<?php echo htmlspecialchars($d); ?>"><?php echo htmlspecialchars($d); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" name="move" style="padding: 2px 5px;">移动</button>
                                        </form>
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
                        <div class="gallery-item">
                            <?php if ($is_image): ?>
                                <a href="<?php echo htmlspecialchars($file_path); ?>" target="_blank">
                                    <img src="<?php echo htmlspecialchars($file_path); ?>" alt="<?php echo htmlspecialchars($file); ?>">
                                </a>
                            <?php else: ?>
                                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 200 150'%3E%3Crect width='100%25' height='100%25' fill='%23e0e0e0'/%3E%3Ctext x='100' y='75' text-anchor='middle' dominant-baseline='middle' font-family='Arial' font-size='14' fill='%23555'%3ENon-image File%3C/text%3E%3C/svg%3E" alt="非图片文件">
                            <?php endif; ?>
                            <div class="item-info">
                                <div class="item-name"><?php echo htmlspecialchars($file); ?></div>
                                <?php if ($is_image): ?>
                                    <div class="item-details">
                                        <?php echo $file_details['dimensions']; ?> | <?php echo $file_details['size']; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="item-actions">
                                    <?php if ($is_image): ?>
                                        <a href="<?php echo htmlspecialchars($file_path); ?>" target="_blank">查看原图</a>
                                    <?php endif; ?>
                                    
                                    <!-- 文件操作表单（仅对管理员可见） -->
                                    <?php if ($is_admin): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="old_name" value="<?php echo htmlspecialchars($file); ?>">
                                            <input type="text" name="new_name" value="<?php echo htmlspecialchars($file); ?>" size="8">
                                            <button type="submit" name="rename" style="padding: 2px 5px;">重命名</button>
                                        </form>
                                        
                                        <form method="post" style="display:inline;" onsubmit="return confirm('确定要删除此文件吗？');">
                                            <input type="hidden" name="delete_path" value="<?php echo htmlspecialchars($file); ?>">
                                            <button type="submit" name="delete" style="padding: 2px 5px;">删除</button>
                                        </form>
                                        
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="move_source" value="<?php echo htmlspecialchars($file); ?>">
                                            <select name="move_target" style="padding: 2px;">
                                                <?php foreach ($all_dirs as $d): ?>
                                                    <option value="<?php echo htmlspecialchars($d); ?>"><?php echo htmlspecialchars($d); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" name="move" style="padding: 2px 5px;">移动</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script>
        // 图片预览功能
        document.getElementById('image').addEventListener('change', function(e) {
            const preview = document.getElementById('uploadPreview');
            preview.innerHTML = '';
            
            const files = e.target.files;
            if (files) {
                Array.from(files).forEach(file => {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.alt = file.name;
                        preview.appendChild(img);
                    }
                    
                    reader.readAsDataURL(file);
                });
            }
        });
    </script>
</body>
</html>    
