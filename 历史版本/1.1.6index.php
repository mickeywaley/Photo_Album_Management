<?php
$config = [
    'admin_password' => 'admin123',
    'upload_dir' => 'albums',
    'thumb_dir' => 'thumbnails', // 缩略图统一目录
    'thumb_width' => 400,       // 缩略图宽度（适配原有卡片）
    'thumb_quality' => 85       // 缩略图质量（保证清晰）
];

// 自动创建目录
if (!file_exists($config['upload_dir'])) mkdir($config['upload_dir'], 0777, true);
if (!file_exists($config['thumb_dir'])) mkdir($config['thumb_dir'], 0777, true);

// 底部配置文件
$link_file = __DIR__.'/links.txt';
$copyright_file = __DIR__.'/copyright.txt';
$github_file = __DIR__.'/github.txt';
if(!file_exists($link_file)) file_put_contents($link_file, "百度=https://www.baidu.com\n谷歌=https://www.google.com");
if(!file_exists($copyright_file)) file_put_contents($copyright_file, "© 2026 相册管理系统 版权所有");
if(!file_exists($github_file)) file_put_contents($github_file, "https://github.com/yourname/album-system");
$links = file($link_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$copyright = trim(file_get_contents($copyright_file));
$github = trim(file_get_contents($github_file));

session_start();
$is_admin = isset($_SESSION['admin']) && $_SESSION['admin'] === true;

// 登录退出
if (isset($_POST['login']) && $_POST['password'] === $config['admin_password']) {
    $_SESSION['admin'] = true;
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}
if (isset($_GET['logout'])) {
    unset($_SESSION['admin']);
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

// 保存底部设置
if($is_admin && isset($_POST['save_links'])){ file_put_contents($link_file, $_POST['links']); header('Location: '.$_SERVER['PHP_SELF']); exit; }
if($is_admin && isset($_POST['save_copyright'])){ file_put_contents($copyright_file, $_POST['copyright']); header('Location: '.$_SERVER['PHP_SELF']); exit; }
if($is_admin && isset($_POST['save_github'])){ file_put_contents($github_file, $_POST['github']); header('Location: '.$_SERVER['PHP_SELF']); exit; }

// 当前目录
$current_dir = $config['upload_dir'];
$current_thumb_dir = $config['thumb_dir'];
if (isset($_GET['dir'])) {
    $requested_dir = $config['upload_dir'].'/'.trim($_GET['dir'], '/');
    if (is_dir($requested_dir) && strpos(realpath($requested_dir), realpath($config['upload_dir'])) === 0) {
        $current_dir = $requested_dir;
        $path = ltrim(str_replace($config['upload_dir'], '', $requested_dir), '/');
        $current_thumb_dir = $config['thumb_dir'] . '/' . $path;
    }
}

// ======================================
// 缩略图生成核心函数（自动检查 + 生成）
// 保持图片比例，不裁剪，完整显示
// ======================================
function get_thumbnail($src_path, $thumb_path, $max_width, $quality) {
    // 创建缩略图目录（如果不存在）
    if (!file_exists(dirname($thumb_path))) {
        mkdir(dirname($thumb_path), 0777, true);
    }
    
    // 有缩略图直接返回
    if (file_exists($thumb_path)) {
        return $thumb_path;
    }

    // 获取原图信息
    $info = @getimagesize($src_path);
    if (!$info) {
        return $src_path; // 非图片文件直接返回原图
    }
    list($orig_w, $orig_h, $type) = $info;

    // 计算缩略图尺寸（保持比例，不裁剪，最大宽度为设定值）
    $ratio = $max_width / $orig_w;
    $new_w = $max_width;
    $new_h = (int)($orig_h * $ratio);

    // 按图片类型创建原图资源
    switch ($type) {
        case 1: // GIF
            $src = imagecreatefromgif($src_path);
            break;
        case 2: // JPEG
            $src = imagecreatefromjpeg($src_path);
            break;
        case 3: // PNG
            $src = imagecreatefrompng($src_path);
            // 保留PNG透明背景
            imagesavealpha($src, true);
            $transparency = imagecolorallocatealpha($src, 0, 0, 0, 127);
            imagefill($src, 0, 0, $transparency);
            break;
        case 18: // WebP
            $src = imagecreatefromwebp($src_path);
            break;
        default:
            return $src_path;
    }

    // 创建缩略图画布（保持比例，不裁剪）
    $dst = imagecreatetruecolor($new_w, $new_h);
    
    // PNG/WebP 透明背景处理
    if ($type === 3 || $type === 18) {
        imagesavealpha($dst, true);
        $transparency = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefill($dst, 0, 0, $transparency);
    }

    // 缩放图片（保持比例，完整显示）
    imagecopyresampled(
        $dst, $src,
        0, 0, 0, 0,
        $new_w, $new_h,
        $orig_w, $orig_h
    );

    // 保存缩略图
    switch ($type) {
        case 1:
            imagegif($dst, $thumb_path);
            break;
        case 2:
            imagejpeg($dst, $thumb_path, $quality);
            break;
        case 3:
            imagepng($dst, $thumb_path);
            break;
        case 18:
            imagewebp($dst, $thumb_path, $quality);
            break;
    }

    // 释放资源
    imagedestroy($src);
    imagedestroy($dst);
    
    return $thumb_path;
}

// 相册密码验证
$album_password_file = $current_dir.'/.password';
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
            header('Location: '.$_SERVER['PHP_SELF'].'?'.http_build_query($_GET));
            exit;
        } else {
            $message = '密码错误';
        }
    }
} else {
    $album_access_granted = true;
}

// 处理文件上传（仅管理员）
if ($is_admin && isset($_POST['upload']) && !empty($_FILES['image']['name'][0])) {
    $uploaded_count = 0;
    $failed_count = 0;
    
    for ($i=0; $i<count($_FILES['image']['name']); $i++) {
        $file_name = $_FILES['image']['name'][$i];
        $file_tmp = $_FILES['image']['tmp_name'][$i];
        $file_error = $_FILES['image']['error'][$i];
        
        if ($file_error === UPLOAD_ERR_OK) {
            $upload_path = $current_dir . '/' . basename($file_name);
            $allowed_types = array('image/jpeg','image/png','image/gif','image/webp');
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
    
    $message = "成功上传 {$uploaded_count} 个文件";
    if ($failed_count > 0) {
        $message .= "，{$failed_count} 个文件上传失败";
    }
}

// 新建目录（仅管理员）
if ($is_admin && isset($_POST['new_dir'])) {
    $new_dir = $current_dir . '/' . trim($_POST['new_dir_name']);
    if (!file_exists($new_dir) && mkdir($new_dir, 0777, true)) {
        $message = '目录创建成功';
    } else {
        $message = '目录创建失败';
    }
}

// 设置相册密码（仅管理员）
if ($is_admin && isset($_POST['set_album_password'])) {
    $password = trim($_POST['album_password']);
    $password_file = $current_dir . '/.password';
    if (empty($password)) {
        if (file_exists($password_file)) {
            unlink($password_file);
        }
        $message = '相册密码已移除';
    } else {
        file_put_contents($password_file, password_hash($password, PASSWORD_DEFAULT));
        $message = '相册密码已设置';
    }
}

// 重命名（仅管理员）
if ($is_admin && isset($_POST['rename'])) {
    $old = $current_dir . '/' . $_POST['old_name'];
    $new = $current_dir . '/' . $_POST['new_name'];
    if (rename($old, $new)) {
        // 同步重命名缩略图
        $old_thumb = str_replace($config['upload_dir'], $config['thumb_dir'], $old);
        $new_thumb = str_replace($config['upload_dir'], $config['thumb_dir'], $new);
        if (file_exists($old_thumb)) {
            rename($old_thumb, $new_thumb);
        }
        $message = '重命名成功';
    } else {
        $message = '重命名失败';
    }
}

// 删除（仅管理员）
if ($is_admin && isset($_POST['delete'])) {
    $path = $current_dir . '/' . $_POST['delete_path'];
    if (is_file($path)) {
        // 删除原图
        unlink($path);
        // 删除对应缩略图
        $thumb_path = str_replace($config['upload_dir'], $config['thumb_dir'], $path);
        if (file_exists($thumb_path)) {
            unlink($thumb_path);
        }
        $message = '文件删除成功';
    } elseif (is_dir($path)) {
        // 递归删除目录及文件
        function deleteDirectory($dir) {
            if (!file_exists($dir)) return true;
            if (!is_dir($dir) || is_link($dir)) return unlink($dir);
            foreach (scandir($dir) as $item) {
                if ($item == '.' || $item == '..') continue;
                if (!deleteDirectory($dir . '/' . $item)) {
                    chmod($dir . '/' . $item, 0777);
                    deleteDirectory($dir . '/' . $item);
                }
            }
            return rmdir($dir);
        }
        // 删除原图目录
        deleteDirectory($path);
        // 删除对应缩略图目录
        $thumb_dir = str_replace($config['upload_dir'], $config['thumb_dir'], $path);
        if (file_exists($thumb_dir)) {
            deleteDirectory($thumb_dir);
        }
        $message = '目录删除成功';
    }
}

// 读取目录内容（目录和图片分开）
$dirs = array();
$files = array();
$current_dir_contents = scandir($current_dir);
foreach ($current_dir_contents as $item) {
    if ($item === '.' || $item === '..' || $item === '.password' || $item === '.preview') continue;
    
    $item_path = $current_dir . '/' . $item;
    if (is_dir($item_path)) {
        $dirs[] = $item;
    } elseif (is_file($item_path)) {
        // 只保留图片文件
        $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
        if (in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'webp'))) {
            $files[] = $item;
        }
    }
}

// 整理所有图片（用于预览切换）
$all_images = array();
foreach ($files as $f) {
    $all_images[] = $current_dir . '/' . $f;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>相册管理系统</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
    <script>
        tailwind.config = { darkMode: 'class' }
    </script>
    <style>
        .blur-lock { filter: blur(10px) brightness(0.6); }
        .img-box { aspect-ratio: 1/1; object-fit: contain; background-color: #f3f4f6; dark:background-color: #1f2937; }
        .edit-input { border: 1px solid #d1d5db; }
        .dark .edit-input { background: #374151; border-color: #4b5563; color: #fff; }
        .card-transition { transition: all 0.3s ease; }
        .scale-hover { transition: transform 0.2s ease-in-out; }
        .scale-hover:hover { transform: scale(1.03); }
        /* 关闭按钮样式优化 */
        .close-btn {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            transition: all 0.2s ease;
            z-index: 9999; /* 最高层级，避免被覆盖 */
        }
        .close-btn:hover {
            background-color: rgba(239, 68, 68, 0.9); /*  hover 变红，更醒目 */
            transform: scale(1.1);
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 dark:text-white transition-colors min-h-screen flex flex-col">

<div class="max-w-7xl mx-auto px-4 py-6 flex-grow">
    <!-- 顶部导航 -->
    <div class="flex justify-between items-center bg-white dark:bg-gray-800 p-4 rounded-xl shadow mb-4">
        <h1 class="text-xl font-bold text-blue-500 cursor-pointer" onclick="window.location.href='?'">📷 相册管理系统</h1>
        <div class="flex gap-3">
            <button id="toggleDark" class="p-2 rounded-lg bg-gray-100 dark:bg-gray-700">
                <i class="fa fa-moon-o dark:hidden"></i>
                <i class="fa fa-sun-o hidden dark:inline"></i>
            </button>
            <?php if ($is_admin): ?>
                <a href="?logout=1" class="px-4 py-2 bg-blue-500 text-white rounded">退出</a>
            <?php else: ?>
                <a href="?login=1" class="px-4 py-2 bg-blue-500 text-white rounded">登录</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 面包屑路径 -->
    <div class="text-sm text-gray-600 dark:text-gray-300 mb-6">
        <a href="?" class="text-blue-500">首页</a>
        <?php
        $cur_path = '';
        $paths = explode('/', substr($current_dir, strlen($config['upload_dir'])+1));
        foreach ($paths as $pt) {
            if (empty($pt)) continue;
            $cur_path .= '/'.$pt;
            echo ' <span class="mx-1">/</span> <a href="?dir='.urlencode(ltrim($cur_path,'/')).'" class="text-blue-500">'.htmlspecialchars($pt).'</a>';
        }
        ?>
    </div>

    <!-- 消息提示 -->
    <?php if (isset($message)): ?>
        <div class="p-3 bg-green-100 dark:bg-green-900 rounded mb-4">
            <i class="fa fa-check-circle mr-2"></i><?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- 登录表单 -->
    <?php if (isset($_GET['login']) && !$is_admin): ?>
        <div class="max-w-md mx-auto bg-white dark:bg-gray-800 p-6 rounded-xl">
            <h3 class="text-lg font-bold mb-3">管理员登录</h3>
            <form method="post">
                <input type="password" name="password" class="w-full border p-2 rounded my-3 dark:bg-gray-700 dark:text-white">
                <button class="w-full bg-blue-500 text-white p-2 rounded" name="login">登录</button>
            </form>
        </div>
    <?php elseif ($album_is_protected && !$album_access_granted): ?>
        <!-- 相册密码表单 -->
        <div class="max-w-md mx-auto bg-white dark:bg-gray-800 p-6 rounded-xl">
            <h3 class="text-lg font-bold mb-3">请输入相册密码</h3>
            <form method="post">
                <input type="password" name="album_password" class="w-full border p-2 rounded my-3 dark:bg-gray-700 dark:text-white">
                <button class="w-full bg-blue-500 text-white p-2 rounded">解锁</button>
            </form>
        </div>
    <?php else: ?>

        <!-- 管理员操作面板（恢复原有布局） -->
        <?php if ($is_admin): ?>
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl mb-6">
                <h3 class="mb-3 font-bold">管理操作</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- 上传图片 -->
                    <div class="border dark:border-gray-700 rounded-lg p-4">
                        <h4 class="font-medium mb-3 text-blue-500">
                            <i class="fa fa-upload mr-2"></i>上传图片
                        </h4>
                        <form id="uploadForm" method="post" enctype="multipart/form-data">
                            <input type="file" name="image[]" id="fileInput" multiple accept="image/*" class="mb-3">
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 mb-3 hidden" id="progressWrap">
                                <div id="progressBar" class="h-2 rounded-full bg-blue-500" style="width:0%"></div>
                            </div>
                            <button type="submit" name="upload" class="w-full bg-blue-500 text-white p-2 rounded">开始上传</button>
                        </form>
                    </div>

                    <!-- 新建目录 -->
                    <div class="border dark:border-gray-700 rounded-lg p-4">
                        <h4 class="font-medium mb-3 text-blue-500">
                            <i class="fa fa-folder mr-2"></i>新建目录
                        </h4>
                        <form method="post">
                            <input type="text" name="new_dir_name" class="w-full border p-2 rounded my-2 dark:bg-gray-700 dark:text-white">
                            <button name="new_dir" class="w-full bg-blue-500 text-white p-2 rounded">创建</button>
                        </form>
                    </div>

                    <!-- 相册密码 -->
                    <div class="border dark:border-gray-700 rounded-lg p-4">
                        <h4 class="font-medium mb-3 text-blue-500">
                            <i class="fa fa-lock mr-2"></i>相册密码
                        </h4>
                        <form method="post">
                            <input type="password" name="album_password" placeholder="留空=删除" class="w-full border p-2 rounded my-2 dark:bg-gray-700 dark:text-white">
                            <button name="set_album_password" class="w-full bg-blue-500 text-white p-2 rounded">保存</button>
                        </form>
                    </div>
                </div>

                <!-- 底部设置（友情链接/版权/GitHub） -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
                    <div class="border dark:border-gray-700 rounded-lg p-4">
                        <h4 class="font-medium mb-3 text-blue-500">
                            <i class="fa fa-link mr-2"></i>友情链接
                        </h4>
                        <form method="post">
                            <textarea name="links" rows="3" class="w-full border p-2 rounded my-2 dark:bg-gray-700 dark:text-white"><?php echo htmlspecialchars(file_get_contents($link_file)); ?></textarea>
                            <button name="save_links" class="w-full bg-blue-500 text-white p-2 rounded">保存</button>
                        </form>
                    </div>

                    <div class="border dark:border-gray-700 rounded-lg p-4">
                        <h4 class="font-medium mb-3 text-blue-500">
                            <i class="fa fa-copyright mr-2"></i>版权信息
                        </h4>
                        <form method="post">
                            <input name="copyright" value="<?php echo htmlspecialchars($copyright); ?>" class="w-full border p-2 rounded my-2 dark:bg-gray-700 dark:text-white">
                            <button name="save_copyright" class="w-full bg-blue-500 text-white p-2 rounded">保存</button>
                        </form>
                    </div>

                    <div class="border dark:border-gray-700 rounded-lg p-4">
                        <h4 class="font-medium mb-3 text-blue-500">
                            <i class="fa fa-github mr-2"></i>GitHub 地址
                        </h4>
                        <form method="post">
                            <input name="github" value="<?php echo htmlspecialchars($github); ?>" class="w-full border p-2 rounded my-2 dark:bg-gray-700 dark:text-white">
                            <button name="save_github" class="w-full bg-blue-500 text-white p-2 rounded">保存</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- 相册内容（恢复原有卡片布局，图片完整显示） -->
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-5">
            <!-- 显示目录 -->
            <?php foreach ($dirs as $dir):
                $dir_path = $current_dir.'/'.$dir;
                $lock = file_exists($dir_path.'/.password');
                $preview_image = null;
                
                // 获取目录预览图（优先第一张图片）
                $dir_contents = scandir($dir_path);
                foreach ($dir_contents as $item) {
                    if ($item === '.' || $item === '..' || $item === '.password') continue;
                    $item_path = $dir_path.'/'.$item;
                    if (is_file($item_path) && @getimagesize($item_path)) {
                        $preview_image = $item_path;
                        break;
                    }
                }
            ?>
            <div class="bg-white dark:bg-gray-800 rounded-xl overflow-hidden shadow scale-hover card-transition">
                <div class="relative">
                    <?php if ($lock): ?>
                        <div class="absolute top-2 right-2 text-xs bg-black/50 text-white px-2 py-1 rounded z-10">加密</div>
                    <?php endif; ?>
                    <a href="?dir=<?php echo urlencode(substr($dir_path, strlen($config['upload_dir'])+1)); ?>">
                        <?php if ($preview_image): ?>
                            <?php
                            // 生成目录预览图的缩略图
                            $preview_thumb = str_replace($config['upload_dir'], $config['thumb_dir'], $preview_image);
                            $show_preview = get_thumbnail($preview_image, $preview_thumb, $config['thumb_width'], $config['thumb_quality']);
                            ?>
                            <img src="<?php echo $show_preview; ?>" class="img-box w-full <?php echo $lock?'blur-lock':''; ?>">
                        <?php else: ?>
                            <div class="img-box flex items-center justify-center bg-gray-100 dark:bg-gray-700">
                                <i class="fa fa-folder text-4xl text-gray-300"></i>
                            </div>
                        <?php endif; ?>
                    </a>
                </div>
                <div class="p-3">
                    <p class="truncate font-medium"><?php echo $dir; ?></p>
                    <?php if ($is_admin): ?>
                    <div class="mt-2 flex gap-2 text-sm">
                        <form method="post" class="flex gap-1">
                            <input type="hidden" name="old_name" value="<?php echo htmlspecialchars($dir); ?>">
                            <input type="text" name="new_name" value="<?php echo htmlspecialchars($dir); ?>" class="w-20 edit-input p-1 rounded text-sm">
                            <button type="submit" name="rename" class="text-blue-500"><i class="fa fa-pencil"></i></button>
                        </form>
                        <button onclick="del('<?php echo htmlspecialchars($dir); ?>','dir')" class="text-red-500"><i class="fa fa-trash"></i></button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- 显示图片（使用缩略图，完整显示） -->
            <?php foreach ($files as $k => $f):
                $src_path = $current_dir . '/' . $f;
                $thumb_path = str_replace($config['upload_dir'], $config['thumb_dir'], $src_path);
                // 获取缩略图（自动生成/使用已有）
                $show_img = get_thumbnail($src_path, $thumb_path, $config['thumb_width'], $config['thumb_quality']);
            ?>
            <div class="bg-white dark:bg-gray-800 rounded-xl overflow-hidden shadow scale-hover card-transition">
                <div class="relative">
                    <!-- 点击打开预览（加载原图） -->
                    <img src="<?php echo $show_img; ?>" class="img-box w-full cursor-pointer" onclick="openViewer(<?php echo $k; ?>)">
                </div>
                <div class="p-3">
                    <p class="truncate font-medium"><?php echo pathinfo($f, PATHINFO_FILENAME); ?></p>
                    <?php if ($is_admin): ?>
                    <div class="mt-2 flex gap-2 text-sm">
                        <form method="post" class="flex gap-1">
                            <input type="hidden" name="old_name" value="<?php echo htmlspecialchars($f); ?>">
                            <input type="text" name="new_name" value="<?php echo htmlspecialchars($f); ?>" class="w-20 edit-input p-1 rounded text-sm">
                            <button type="submit" name="rename" class="text-blue-500"><i class="fa fa-pencil"></i></button>
                        </form>
                        <button onclick="del('<?php echo htmlspecialchars($f); ?>','file')" class="text-red-500"><i class="fa fa-trash"></i></button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- 底部友情链接 -->
<div class="bg-white dark:bg-gray-800 py-4 border-t dark:border-gray-700">
    <div class="max-w-7xl mx-auto px-4 text-center">
        <h3 class="text-sm font-bold mb-2">友情链接</h3>
        <div class="flex flex-wrap justify-center gap-3 text-sm">
            <?php foreach($links as $line){
                $item = explode('=', $line, 2);
                if(count($item)==2){
                    $name = trim($item[0]);
                    $url = trim($item[1]);
                    echo "<a href='$url' target='_blank' class='text-blue-500 hover:underline'>$name</a>";
                }
            } ?>
        </div>
    </div>
</div>

<!-- 最底部版权 + GitHub -->
<div class="bg-gray-100 dark:bg-gray-800 py-3 text-sm text-center text-gray-600 dark:text-gray-300 border-t dark:border-gray-700">
    <p><?php echo $copyright; ?></p>
    <p class="mt-1">
        <a href="<?php echo $github; ?>" target="_blank" class="inline-flex items-center gap-1 text-blue-500 hover:underline">
            <i class="fa fa-github"></i> GitHub 项目地址
        </a>
    </p>
</div>

<!-- 全屏预览器（核心修复：关闭按钮） -->
<div id="viewer" class="fixed inset-0 bg-black/95 z-50 hidden flex flex-col items-center justify-center">
    <!-- 右上角功能按钮组（分离关闭按钮，避免冲突） -->
    <div class="absolute top-4 right-4 flex gap-3 items-center">
        <!-- 查看原图 + 下载 -->
        <div class="flex gap-3">
            <a id="viewOriginal" target="_blank" class="text-white bg-blue-600 px-3 py-1 rounded flex items-center">
                <i class="fa fa-eye mr-1"></i>查看原图
            </a>
            <a id="downloadBtn" class="text-white bg-green-600 px-3 py-1 rounded flex items-center">
                <i class="fa fa-download mr-1"></i>下载
            </a>
        </div>
        <!-- 关闭按钮（独立设置，放大+独立区域） -->
        <button onclick="closeViewer()" class="close-btn text-white text-5xl">
            ×
        </button>
    </div>

    <!-- 图片区域（调整切换区域，避开关闭按钮） -->
    <div id="swipeArea" class="w-full h-full flex items-center justify-center relative">
        <!-- 左右切换区域（缩小右侧区域，避免和关闭按钮重叠） -->
        <div class="absolute left-0 top-0 w-1/2 h-full z-10" onclick="prev()"></div>
        <div class="absolute right-20 top-0 w-[calc(50%-50px)] h-full z-10" onclick="next()"></div>
        <img id="viewerImg" class="max-h-[90vh] max-w-[90vw]">
    </div>

    <!-- 图片索引 -->
    <div class="absolute bottom-4 text-white bg-black/50 px-3 py-1 rounded z-10">
        <span id="imgIndex">1/1</span>
    </div>
</div>

<script>
    // 深色模式
    const darkBtn = document.getElementById('toggleDark');
    darkBtn.addEventListener('click',()=>{
        document.documentElement.classList.toggle('dark');
        localStorage.setItem('theme', document.documentElement.classList.contains('dark')?'dark':'light');
    });
    if(localStorage.getItem('theme')==='dark') document.documentElement.classList.add('dark');

    // 上传进度
    const form = document.getElementById('uploadForm');
    const fileInput = document.getElementById('fileInput');
    const progressWrap = document.getElementById('progressWrap');
    const progressBar = document.getElementById('progressBar');
    if(form){
        form.addEventListener('submit',(e)=>{
            if(fileInput.files.length>0){
                progressWrap.classList.remove('hidden');
                const total = fileInput.files.length;
                let loaded = 0;
                const interval = setInterval(()=>{
                    loaded++;
                    const per = Math.round((loaded/total)*100);
                    progressBar.style.width = per+'%';
                    if(loaded >= total) clearInterval(interval);
                },300);
            }
        });
    }

    // 图片预览器核心
    const images = <?php echo json_encode($all_images); ?>;
    let index = 0;
    const viewer = document.getElementById('viewer');
    const viewerImg = document.getElementById('viewerImg');
    const downloadBtn = document.getElementById('downloadBtn');
    const viewOriginal = document.getElementById('viewOriginal');
    const imgIndex = document.getElementById('imgIndex');

    // 打开预览（加载原图）
    function openViewer(k){
        index = k;
        updateImage();
        viewer.classList.remove('hidden');
        viewer.focus();
    }

    // 关闭预览
    function closeViewer(){ 
        viewer.classList.add('hidden'); 
    }

    // 上一张/下一张
    function prev(){
        index = (index-1 + images.length) % images.length;
        updateImage();
    }
    function next(){
        index = (index+1) % images.length;
        updateImage();
    }

    // 更新图片信息
    function updateImage(){
        if(images.length === 0) return;
        const src = images[index];
        const fileName = src.split('/').pop();
        
        // 加载原图
        viewerImg.src = src;
        downloadBtn.href = src;
        downloadBtn.download = fileName;
        viewOriginal.href = src;
        
        // 更新索引
        imgIndex.innerText = (index + 1) + '/' + images.length;
    }

    // 键盘控制
    document.addEventListener('keydown',(e)=>{
        if(!viewer.classList.contains('hidden')){
            if(e.key==='ArrowLeft' || e.key==='a') prev();
            if(e.key==='ArrowRight' || e.key==='d') next();
            if(e.key==='Escape') closeViewer();
            e.preventDefault();
        }
    });

    // PC端鼠标拖拽切换
    let mouseStartX = 0;
    const swipeArea = document.getElementById('swipeArea');
    swipeArea.addEventListener('mousedown',(e)=>{
        mouseStartX = e.clientX;
    });
    swipeArea.addEventListener('mouseup',(e)=>{
        const mouseEndX = e.clientX;
        if(mouseEndX < mouseStartX - 50) next();
        if(mouseEndX > mouseStartX + 50) prev();
    });
    viewerImg.addEventListener('dragstart',(e)=>{
        e.preventDefault();
    });

    // 手机端滑动切换
    let touchStartX = 0;
    swipeArea.addEventListener('touchstart',(e)=>{
        touchStartX = e.changedTouches[0].screenX;
    });
    swipeArea.addEventListener('touchend',(e)=>{
        touchEndX = e.changedTouches[0].screenX;
        if(touchEndX < touchStartX - 50) next();
        if(touchEndX > touchStartX + 50) prev();
    });

    // 点击图片切换（左半部分上一张，右半部分下一张）
    viewerImg.addEventListener('click',(e)=>{
        const imgWidth = viewerImg.offsetWidth;
        const clickX = e.clientX - viewerImg.getBoundingClientRect().left;
        if(clickX < imgWidth / 2){
            prev();
        } else {
            next();
        }
    });

    // 点击空白区域关闭预览
    viewer.addEventListener('click',(e)=>{
        if(e.target === viewer){
            closeViewer();
        }
    });

    // 删除功能
    function del(name, type){
        if(confirm('确定删除 ' + (type==='dir'?'目录':'文件') + '：' + name + '？')){
            const f = document.createElement('form');
            f.method='POST';
            f.innerHTML='<input type="hidden" name="delete" value="1"><input type="hidden" name="delete_path" value="'+name+'">';
            document.body.appendChild(f);
            f.submit();
        }
    }
</script>
</body>
</html>
