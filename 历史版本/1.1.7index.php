<?php
$config = [
    'admin_password' => 'admin123',
    'upload_dir' => 'albums',
    'thumb_dir' => 'thumbnails',
    'thumb_width' => 400,
    'thumb_quality' => 85
];

if (!file_exists($config['upload_dir'])) mkdir($config['upload_dir'], 0777, true);
if (!file_exists($config['thumb_dir'])) mkdir($config['thumb_dir'], 0777, true);

$link_file = __DIR__.'/links.txt';
$copyright_file = __DIR__.'/copyright.txt';
$github_file = __DIR__.'/github.txt';
if(!file_exists($link_file)) file_put_contents($link_file, "首页=https://\n百度=https://www.baidu.com");
if(!file_exists($copyright_file)) file_put_contents($copyright_file, "© 相册系统");
if(!file_exists($github_file)) file_put_contents($github_file, "https://github.com");

$links = file($link_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$copyright = trim(file_get_contents($copyright_file));
$github = trim(file_get_contents($github_file));

session_start();
$is_admin = isset($_SESSION['admin']) && $_SESSION['admin'] === true;

if (isset($_POST['login']) && $_POST['password'] === $config['admin_password']) {
    $_SESSION['admin'] = true;
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

if($is_admin && isset($_POST['save_links'])){ file_put_contents($link_file, $_POST['links']); header('Location: '.$_SERVER['REQUEST_URI']);exit; }
if($is_admin && isset($_POST['save_copyright'])){ file_put_contents($copyright_file, $_POST['copyright']); header('Location: '.$_SERVER['REQUEST_URI']);exit; }
if($is_admin && isset($_POST['save_github'])){ file_put_contents($github_file, $_POST['github']); header('Location: '.$_SERVER['REQUEST_URI']);exit; }

$current_dir = $config['upload_dir'];
$current_thumb_dir = $config['thumb_dir'];
if (isset($_GET['dir'])) {
    $req = $config['upload_dir'].'/'.trim($_GET['dir'],'/');
    $real_upload = realpath($config['upload_dir']);
    $real_req = realpath($req);
    if(is_dir($req) && $real_req && $real_upload && strpos($real_req, $real_upload) === 0){
        $current_dir = $req;
        $current_thumb_dir = $config['thumb_dir'].'/'.trim($_GET['dir'],'/');
    }
}

function create_thumbnail($src, $thumb, $w, $q) {
    if (!file_exists($src)) return false;
    if (file_exists($thumb)) return true;
    
    $thumb_dir = dirname($thumb);
    if (!file_exists($thumb_dir)) mkdir($thumb_dir, 0777, true);
    
    $info = getimagesize($src);
    if (!$info) return false;
    
    list($ow, $oh, $type) = $info;
    $ratio = $w / $ow;
    $nw = $w;
    $nh = (int)($oh * $ratio);
    
    switch ($type) {
        case 1: $im = imagecreatefromgif($src); break;
        case 2: $im = imagecreatefromjpeg($src); break;
        case 3: $im = imagecreatefrompng($src); imagesavealpha($im, true); break;
        case 18: $im = imagecreatefromwebp($src); break;
        default: return false;
    }
    
    $dst = imagecreatetruecolor($nw, $nh);
    if ($type == 3 || $type == 18) {
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0,0,0,127));
    }
    
    imagecopyresampled($dst, $im, 0,0,0,0, $nw,$nh, $ow,$oh);
    
    switch ($type) {
        case 1: imagegif($dst, $thumb); break;
        case 2: imagejpeg($dst, $thumb, $q); break;
        case 3: imagepng($dst, $thumb); break;
        case 18: imagewebp($dst, $thumb, $q); break;
    }
    
    imagedestroy($im);
    imagedestroy($dst);
    return true;
}

function get_thumb_path($src, $upload_dir, $thumb_dir) {
    return str_replace($upload_dir, $thumb_dir, $src);
}

$album_locked = file_exists($current_dir.'/.password');
$album_ok = true;
if($album_locked){
    $album_ok = isset($_SESSION['album'][$current_dir]);
    if(isset($_POST['pass'])){
        if(password_verify($_POST['pass'],file_get_contents($current_dir.'/.password'))){
            $_SESSION['album'][$current_dir]=true;
            header('Location:'.$_SERVER['REQUEST_URI']);
            exit;
        }
    }
}

if($is_admin && isset($_POST['upload'])){
    foreach($_FILES['image']['tmp_name'] as $i=>$tmp){
        $name = basename($_FILES['image']['name'][$i]);
        $target = $current_dir.'/'.$name;
        if($tmp && getimagesize($tmp)){
            move_uploaded_file($tmp, $target);
            $thumb = get_thumb_path($target, $config['upload_dir'], $config['thumb_dir']);
            create_thumbnail($target, $thumb, $config['thumb_width'], $config['thumb_quality']);
        }
    }
    header('Location:'.$_SERVER['REQUEST_URI']);
    exit;
}

if($is_admin && isset($_POST['new_dir'])){
    $d=$current_dir.'/'.$_POST['dname'];
    if(!file_exists($d))mkdir($d,0777,true);
    header('Location:'.$_SERVER['REQUEST_URI']);exit;
}

if($is_admin && isset($_POST['set_album_pass'])){
    $f=$current_dir.'/.password';
    empty($_POST['pass'])?@unlink($f):file_put_contents($f,password_hash($_POST['pass'],PASSWORD_DEFAULT));
    header('Location:'.$_SERVER['REQUEST_URI']);exit;
}

if($is_admin && isset($_POST['rename'])){
    $o=$current_dir.'/'.$_POST['old'];
    $n=$current_dir.'/'.$_POST['new'];
    rename($o,$n);
    $t1=str_replace($config['upload_dir'],$config['thumb_dir'],$o);
    $t2=str_replace($config['upload_dir'],$config['thumb_dir'],$n);
    if(file_exists($t1))rename($t1,$t2);
    header('Location:'.$_SERVER['REQUEST_URI']);exit;
}

if($is_admin && isset($_POST['delete'])){
    $t=$_POST['target'];
    $p=$current_dir.'/'.$t;
    if(is_file($p)){
        unlink($p);
        $tfile = str_replace($config['upload_dir'],$config['thumb_dir'],$p);
        if(file_exists($tfile)) unlink($tfile);
    }else{
        function r($d){
            $h=scandir($d);
            foreach($h as $i){
                if($i=='.'||$i=='..')continue;
                $f=$d.'/'.$i;
                is_dir($f)?r($f):unlink($f);
            }
            rmdir($d);
        }
        r($p);
        $tpath = str_replace($config['upload_dir'],$config['thumb_dir'],$p);
        if(file_exists($tpath)) @r($tpath);
    }
    header('Location:'.$_SERVER['REQUEST_URI']);exit;
}

$dirs=[];$files=[];
foreach(scandir($current_dir) as $i){
    if($i=='.'||$i=='..'||$i=='.password')continue;
    $p=$current_dir.'/'.$i;
    is_dir($p)?$dirs[]=$i:$files[]=$i;
}

$all_imgs=[];
foreach($files as $f) $all_imgs[] = $current_dir.'/'.$f;
$total=count($all_imgs);
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
        .close-btn {
            width: 44px; height: 44px; display: flex; align-items: center; justify-content: center;
            border-radius: 50%; background-color: rgba(0,0,0,0.7); backdrop-filter: blur(4px);
            transition: all 0.2s ease; z-index: 9999;
        }
        .close-btn:hover { background-color: rgba(239,68,68,0.9); transform: scale(1.1); }
        #swipeArea { touch-action: pan-y; user-select: none; -webkit-user-select: none; }

        .photo-item { position: relative; }
        .photo-checkbox {
            position: absolute; top: 10px; left: 10px; z-index: 20;
            width: 18px; height: 18px; display: none;
        }
        .download-btn {
            position: absolute; bottom: 10px; right: 10px; z-index: 20;
            background: #10b981; color: white; font-size: 12px; padding: 3px 8px;
            border-radius: 6px; display: none;
        }
        .select-mode .photo-checkbox,
        .select-mode .download-btn { display: block !important; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/file-saver@2.0.5/dist/FileSaver.min.js"></script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 dark:text-white transition-colors min-h-screen flex flex-col">

<div class="max-w-7xl mx-auto px-4 py-6 flex-grow">
    <div class="flex justify-between items-center bg-white dark:bg-gray-800 p-4 rounded-xl shadow mb-4">
        <h1 class="text-xl font-bold text-blue-500 cursor-pointer" onclick="window.location.href='?'">📷 相册管理系统</h1>
        <div class="flex gap-3 items-center">
            <button id="selectToggle" class="px-3 py-1 bg-gray-200 dark:bg-gray-700 rounded text-sm">选择下载</button>
            <button id="selectAll" class="px-3 py-1 bg-purple-500 text-white rounded text-sm hidden">全选当前目录</button>
            <button id="batchDownload" class="px-3 py-1 bg-green-500 text-white rounded text-sm hidden">打包下载</button>
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

    <div class="text-sm text-gray-600 dark:gray-300 mb-6">
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

    <?php if (isset($_GET['login']) && !$is_admin): ?>
        <div class="max-w-md mx-auto bg-white dark:bg-gray-800 p-6 rounded-xl">
            <h3 class="text-lg font-bold mb-3">管理员登录</h3>
            <form method="post">
                <input type="password" name="password" class="w-full border p-2 rounded my-3 dark:bg-gray-700 dark:text-white">
                <button class="w-full bg-blue-500 text-white p-2 rounded" name="login">登录</button>
            </form>
        </div>
    <?php elseif ($album_locked && !$album_ok): ?>
        <div class="max-w-md mx-auto bg-white dark:bg-gray-800 p-6 rounded-xl">
            <h3 class="text-lg font-bold mb-3">请输入相册密码</h3>
            <form method="post">
                <input type="password" name="pass" class="w-full border p-2 rounded my-3 dark:bg-gray-700 dark:text-white">
                <button class="w-full bg-blue-500 text-white p-2 rounded">解锁</button>
            </form>
        </div>
    <?php else: ?>

        <?php if ($is_admin): ?>
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl mb-6">
                <h3 class="mb-3 font-bold">管理操作</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="border dark:border-gray-700 rounded-lg p-4">
                        <h4 class="font-medium mb-3 text-blue-500"><i class="fa fa-upload mr-2"></i>上传图片</h4>
                        <form method="post" enctype="multipart/form-data">
                            <input type="file" name="image[]" multiple accept="image/*" class="mb-3">
                            <button name="upload" class="w-full bg-blue-500 text-white p-2 rounded">上传</button>
                        </form>
                    </div>
                    <div class="border dark:border-gray-700 rounded-lg p-4">
                        <h4 class="font-medium mb-3 text-blue-500"><i class="fa fa-folder mr-2"></i>新建目录</h4>
                        <form method="post">
                            <input type="text" name="dname" class="w-full border p-2 rounded my-2 dark:bg-gray-700 dark:text-white">
                            <button name="new_dir" class="w-full bg-blue-500 text-white p-2 rounded">创建</button>
                        </form>
                    </div>
                    <div class="border dark:border-gray-700 rounded-lg p-4">
                        <h4 class="font-medium mb-3 text-blue-500"><i class="fa fa-lock mr-2"></i>相册密码</h4>
                        <form method="post">
                            <input type="password" name="pass" placeholder="留空=删除" class="w-full border p-2 rounded my-2 dark:bg-gray-700 dark:text-white">
                            <button name="set_album_pass" class="w-full bg-blue-500 text-white p-2 rounded">保存</button>
                        </form>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
                    <div class="border dark:border-gray-700 rounded-lg p-4">
                        <h4 class="font-medium mb-3 text-blue-500"><i class="fa fa-link mr-2"></i>友情链接</h4>
                        <form method="post">
                            <textarea name="links" rows="3" class="w-full border p-2 rounded my-2 dark:bg-gray-700 dark:text-white"><?php echo htmlspecialchars(file_get_contents($link_file)); ?></textarea>
                            <button name="save_links" class="w-full bg-blue-500 text-white p-2 rounded">保存</button>
                        </form>
                    </div>
                    <div class="border dark:border-gray-700 rounded-lg p-4">
                        <h4 class="font-medium mb-3 text-blue-500"><i class="fa fa-copyright mr-2"></i>版权信息</h4>
                        <form method="post">
                            <input name="copyright" value="<?php echo htmlspecialchars($copyright); ?>" class="w-full border p-2 rounded my-2 dark:bg-gray-700 dark:text-white">
                            <button name="save_copyright" class="w-full bg-blue-500 text-white p-2 rounded">保存</button>
                        </form>
                    </div>
                    <div class="border dark:border-gray-700 rounded-lg p-4">
                        <h4 class="font-medium mb-3 text-blue-500"><i class="fa fa-github mr-2"></i>GitHub</h4>
                        <form method="post">
                            <input name="github" value="<?php echo htmlspecialchars($github); ?>" class="w-full border p-2 rounded my-2 dark:bg-gray-700 dark:text-white">
                            <button name="save_github" class="w-full bg-blue-500 text-white p-2 rounded">保存</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-5">
            <?php foreach ($dirs as $dir):
                $dir_path = $current_dir.'/'.$dir;
                $lock = file_exists($dir_path.'/.password');
                $preview_image = null;
                foreach (scandir($dir_path) as $item) {
                    if ($item === '.' || $item === '..' || $item === '.password') continue;
                    $item_path = $dir_path.'/'.$item;
                    if (is_file($item_path) && getimagesize($item_path)) {
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
                    <a href="?dir=<?php echo urlencode(str_replace($config['upload_dir'].'/', '', $dir_path)); ?>">
                        <?php if ($preview_image):
                            $show = get_thumb_path($preview_image, $config['upload_dir'], $config['thumb_dir']);
                            if (!file_exists($show)) {
                                create_thumbnail($preview_image, $show, $config['thumb_width'], $config['thumb_quality']);
                            }
                        ?>
                            <img src="<?php echo $show; ?>" class="img-box w-full <?php echo $lock ? 'blur-lock' : ''; ?>">
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
                            <input type="hidden" name="old" value="<?php echo htmlspecialchars($dir); ?>">
                            <input type="text" name="new" value="<?php echo htmlspecialchars($dir); ?>" class="w-20 edit-input p-1 rounded text-sm">
                            <button type="submit" name="rename" class="text-blue-500"><i class="fa fa-pencil"></i></button>
                        </form>
                        <button onclick="del('<?php echo htmlspecialchars($dir); ?>','dir')" class="text-red-500"><i class="fa fa-trash"></i></button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php foreach ($files as $k => $f):
                $src = $current_dir . '/' . $f;
                $show = get_thumb_path($src, $config['upload_dir'], $config['thumb_dir']);
                if (!file_exists($show)) {
                    create_thumbnail($src, $show, $config['thumb_width'], $config['thumb_quality']);
                }
            ?>
            <div class="photo-item bg-white dark:bg-gray-800 rounded-xl overflow-hidden shadow scale-hover card-transition" data-path="<?php echo htmlspecialchars($src); ?>">
                <input type="checkbox" class="photo-checkbox">
                <button class="download-btn" onclick="singleDownload(event, this)">下载</button>
                <img src="<?php echo $show; ?>" class="img-box w-full cursor-pointer" onclick="openViewer(<?php echo $k; ?>)">
                <div class="p-3">
                    <p class="truncate font-medium"><?php echo pathinfo($f, PATHINFO_FILENAME); ?></p>
                    <?php if ($is_admin): ?>
                    <div class="mt-2 flex gap-2 text-sm">
                        <form method="post" class="flex gap-1">
                            <input type="hidden" name="old" value="<?php echo htmlspecialchars($f); ?>">
                            <input type="text" name="new" value="<?php echo htmlspecialchars($f); ?>" class="w-20 edit-input p-1 rounded text-sm">
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
<div class="bg-gray-100 dark:bg-gray-800 py-3 text-sm text-center text-gray-600 dark:text-gray-300 border-t dark:border-gray-700">
    <p><?php echo $copyright; ?></p>
    <p class="mt-1">
        <a href="<?php echo $github; ?>" target="_blank" class="inline-flex items-center gap-1 text-blue-500 hover:underline">
            <i class="fa fa-github"></i> GitHub
        </a>
    </p>
</div>

<div id="viewer" class="fixed inset-0 bg-black/95 z-50 hidden flex flex-col items-center justify-center">
    <div class="absolute top-4 right-4 flex gap-3 items-center">
        <a id="viewOriginal" target="_blank" class="text-white bg-blue-600 px-3 py-1 rounded flex items-center text-sm">
            <i class="fa fa-eye mr-1"></i>查看原图
        </a>
        <a id="downloadBtn" class="text-white bg-green-600 px-3 py-1 rounded flex items-center text-sm">
            <i class="fa fa-download mr-1"></i>下载
        </a>
        <button onclick="closeViewer()" class="close-btn text-white text-4xl">×</button>
    </div>
    <div id="swipeArea" class="w-full h-full flex items-center justify-center relative">
        <img id="viewerImg" class="max-h-[90vh] max-w-[90vw]">
    </div>
    <div class="absolute bottom-4 text-white bg-black/50 px-3 py-1 rounded z-10">
        <span id="imgIndex">1/<?php echo $total; ?></span>
    </div>
</div>

<script>
const darkBtn = document.getElementById('toggleDark');
darkBtn.addEventListener('click',()=>{
    document.documentElement.classList.toggle('dark');
    localStorage.setItem('theme', document.documentElement.classList.contains('dark')?'dark':'light');
});
if(localStorage.getItem('theme')==='dark') document.documentElement.classList.add('dark');

const allImages = <?php echo json_encode($all_imgs); ?>;
let currentIndex = 0;
const viewer = document.getElementById('viewer');
const viewerImg = document.getElementById('viewerImg');
const downloadBtn = document.getElementById('downloadBtn');
const viewOriginal = document.getElementById('viewOriginal');
const imgIndex = document.getElementById('imgIndex');

function openViewer(k){
    currentIndex = k;
    const src = allImages[currentIndex];
    viewerImg.src = src;
    downloadBtn.href = src;
    downloadBtn.download = src.split('/').pop();
    viewOriginal.href = src;
    imgIndex.innerText = (currentIndex+1)+'/<?php echo $total; ?>';
    viewer.classList.remove('hidden');
}
function closeViewer(){ viewer.classList.add('hidden'); }
function prevImage(){ currentIndex = (currentIndex - 1 + allImages.length) % allImages.length; openViewer(currentIndex); }
function nextImage(){ currentIndex = (currentIndex + 1) % allImages.length; openViewer(currentIndex); }

document.addEventListener('keydown',e=>{
    if(!viewer.classList.contains('hidden')){
        if(e.key==='ArrowLeft')prevImage();
        if(e.key==='ArrowRight')nextImage();
        if(e.key==='Escape')closeViewer();
    }
});

let startX = 0;
document.getElementById('swipeArea').addEventListener('touchstart',e=>{
    startX = e.changedTouches[0].screenX;
},{passive:true});
document.getElementById('swipeArea').addEventListener('touchend',e=>{
    const diff = e.changedTouches[0].screenX - startX;
    if(diff < -40) nextImage();
    if(diff > 40) prevImage();
},{passive:true});

const selectToggle = document.getElementById('selectToggle');
const selectAll = document.getElementById('selectAll');
const batchDownload = document.getElementById('batchDownload');
let selectMode = false;

selectToggle.onclick = () => {
    selectMode = !selectMode;
    selectToggle.textContent = selectMode ? '退出选择' : '选择下载';
    selectAll.classList.toggle('hidden', !selectMode);
    batchDownload.classList.toggle('hidden', !selectMode);
    document.body.classList.toggle('select-mode', selectMode);
    if(!selectMode){
        document.querySelectorAll('.photo-checkbox').forEach(c => c.checked = false);
    }
};

selectAll.onclick = () => {
    const list = document.querySelectorAll('.photo-checkbox');
    const allChecked = Array.from(list).every(c => c.checked);
    list.forEach(c => c.checked = !allChecked);
};

function singleDownload(e, btn){
    e.stopPropagation();
    const path = btn.closest('.photo-item').dataset.path;
    const a = document.createElement('a');
    a.href = path;
    a.download = path.split('/').pop();
    a.click();
}

batchDownload.onclick = async () => {
    const list = Array.from(document.querySelectorAll('.photo-checkbox:checked'))
        .map(c => c.closest('.photo-item').dataset.path);
    if(list.length === 0) return alert('请选择图片');
    const zip = new JSZip();
    for(let url of list){
        const name = url.split('/').pop();
        const resp = await fetch(url);
        const blob = await resp.blob();
        zip.file(name, blob);
    }
    zip.generateAsync({type:'blob'}).then(blob => saveAs(blob, '相册图片.zip'));
};

document.querySelectorAll('.photo-item').forEach(item => {
    item.onclick = e => {
        if(!selectMode) return;
        if(e.target.tagName === 'INPUT' || e.target.tagName === 'BUTTON') return;
        const checkbox = item.querySelector('.photo-checkbox');
        checkbox.checked = !checkbox.checked;
    };
});

function del(name, type){
    if(confirm('确定删除？')){
        const f = document.createElement('form');
        f.method = 'POST';
        f.innerHTML = '<input type="hidden" name="delete" value="1"><input type="hidden" name="target" value="'+name+'">';
        document.body.appendChild(f);
        f.submit();
    }
}
</script>
</body>
</html>
