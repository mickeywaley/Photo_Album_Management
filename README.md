# Photo_Album_Management


1.1.5
修复漏洞
界面美化
预览图
<img src="https://raw.githubusercontent.com/mickeywaley/Photo_Album_Management/refs/heads/main/1.1.5.png" alt="Mobile wallpaper"   />
-------------------------

-------------------------
1.1.4
相册管理
-------------------------
这个 PHP 程序实现了一个完整的相册管理系统，支持图片上传、浏览、分类管理以及密码保护等功能。系统采用模块化设计，主要包含以下几个核心功能模块：

1. 系统基础设置

配置管理员密码和相册存储目录

自动初始化必要的目录结构

基于会话的管理员认证机制

2. 文件管理功能

批量上传：支持一次选择多张图片上传，并显示上传预览

目录管理：可以创建新目录、重命名或删除现有目录

文件操作：支持文件重命名、删除和移动到其他目录

密码保护：可为特定相册设置独立密码，保护隐私

4. 相册浏览功能

多级目录结构：支持创建任意层级的相册目录

图片预览：自动生成相册封面图，快速浏览相册内容

图片信息：显示每张图片的分辨率（如 "800×600 px"）和文件大小

原图查看：点击可查看图片原始尺寸

6. 安全机制

管理员操作需要密码验证

敏感操作（如删除）有确认提示

目录路径验证防止路径遍历攻击

密码使用 PHP 的 password_hash 安全存储

7. 用户界面

响应式设计，适配不同屏幕尺寸

直观的文件和目录网格布局

面包屑导航，方便定位当前位置

操作结果提示信息

系统整体采用前后端分离的架构思想，前端负责展示和交互，后端负责业务逻辑和文件管理，通过表单提交实现各种功能。代码结构清晰，注释完善，易于维护和扩展。


------------
预览图

<img src="https://raw.githubusercontent.com/mickeywaley/Photo_Album_Management/refs/heads/main/01.png" alt="Mobile wallpaper"   />

<img src="https://raw.githubusercontent.com/mickeywaley/Photo_Album_Management/refs/heads/main/02.png" alt="Mobile wallpaper"   />

<img src="https://raw.githubusercontent.com/mickeywaley/Photo_Album_Management/refs/heads/main/03.png" alt="Mobile wallpaper"   />

<img src="https://raw.githubusercontent.com/mickeywaley/Photo_Album_Management/refs/heads/main/04.png" alt="Mobile wallpaper"   />
