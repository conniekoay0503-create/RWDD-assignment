# E-Waste & Repair Café — Group 52 (Admin part: Koay Shin Shee, TP090995)

## 1. 在 XAMPP 运行
1. 把整个 `ewaste` 文件夹放进 `C:\xampp\htdocs\`。
2. 打开 XAMPP Control Panel → Start **Apache** 和 **MySQL**。
3. 打开 `http://localhost/phpmyadmin` → 上方 **Import** → 选 `database/ewaste_repair_cafe.sql` → Go。
   （这个文件会自己建 database `ewaste_repair_cafe`，重新 import 会清掉旧资料，回到 demo 状态。）
4. 打开 `http://localhost/ewaste/` 会先看到首页，再按 Log in / Sign up。

如果 MySQL 有设密码，或 port 不是 3306，改 `config/db.php`。

## 2. Demo 账号（密码全部是 `Password123`）
| Role | Email |
|---|---|
| Admin | koay@mail.apu.edu.my / aisyah@mail.apu.edu.my |
| Officer | jerald@mail.apu.edu.my |
| Volunteer | lim@mail.apu.edu.my |
| Student | genieve@mail.apu.edu.my, daniel@…, priya@…, meiling@…, arjun@…, siti@… |
| Inactive（不能登入） | ken@mail.apu.edu.my |

Demo 资料的日期都是「import 那一刻往前推」，所以随时 import 都会有 1 台 overdue device、1 个进行中的 challenge。

## 3. 文件结构
```
config/db.php            连接 database（mysqli）
includes/functions.php   共用 function：e()、db_all/db_one/db_value/db_exec、notify()、notify_admins()、find_role()、require_role()
includes/public_top.php  login / register 的上半部
includes/admin_top.php   admin 页的上方栏（含药丸形导航 + 下拉子页面）+ page_head()（标题和 tab）；admin_bottom.php 关闭页面
includes/icons.php       icon('名字') 画出 SVG 图标
index.php                首页（未登入时看到；数字、类别、challenge、公告都从 database 读）
login.php register.php logout.php
admin/   每一区（上方导航栏）拆成几页，页面上方有 tab 可以切换：
         index.php                    Dashboard
         categories / category_form / calculator          Categories（列表 / 新增修改 / 计分器）
         challenges / challenge_form                       Challenges（列表 / 新增修改）
         cashback / cashback_history / rewards / reward_form   Cashback（待处理 / 记录 / 奖励列表 / 新增修改）
         users / volunteer_requests / assign_officer       Users（全部用户 / 志愿者申请 / 指派 officer）
         announcements / announcement_form                 Announcements（列表 / 发布修改）
         feedback / feedback_view                          Feedback（收件箱 / 阅读并回复）
         reports, profile, notifications
student/ volunteer/ officer/   placeholder，组员换成自己的页面（保留最上面 require_role 那 3 行）
assets/css/style.css     全站样式（没有 Bootstrap）
assets/js/main.js        全站 JavaScript
assets/img/              首页用的图（自己画的 SVG：手机画面、电路板背景）
assets/fonts/            字体 Bricolage Grotesque（标题、数字）+ Figtree（内文），免费 OFL 授权，放本地所以没网络也能显示
uploads/devices/         student 上传的 device 照片
```

## 4. Presentation 可以讲的重点
- **Prepared statements**：所有 SQL 都用 `?` 再 `bind_param`，用户输入不会变成 SQL 指令 → 防 SQL injection。
- **password_hash / password_verify**：database 只存 hash，看不到真正密码。
- **htmlspecialchars（`e()`）**：印出用户输入前先转义 → 防 XSS。
- **Session + require_role()**：每个 admin 页第一行检查有没有登入、账号是否还 active、role 对不对。
- **Transaction**：一件事要改好几张表时（例如批准 volunteer：更新 request → 删 student → 加 volunteer → 发 notification），用 `begin_transaction()`，任何一步失败就 `rollback()`，不会只做一半。
- **「affected rows = 1」检查**：`UPDATE … WHERE status = 'pending'` 只有还在 pending 才会改到 1 行；如果两个 admin 同时按，第二个会改到 0 行 → 显示「已经被审核了」。
- **PRG（Post → Redirect → Get）+ flash message**：表单送出后 redirect，按 F5 不会重复送出。
- **Overdue 不用定时器**：XAMPP 没有自动排程，所以每次开页面时用 `DATEDIFF(NOW(), status_changed_at) > 7` 当场算。Challenge 的 Upcoming / Running / Ended 也是用今天日期当场算。
- **Client + server validation**：JavaScript 先检查（快），PHP 一定再检查一次（安全，因为 JS 可以被关掉）。
- **Dark mode / 字体大小**：CSS 变量 + `rem`；JavaScript 把选择存进 localStorage（用 try…catch 包住）。

## 5. 给组员：通知 Admin
Student 做了需要 admin 处理的事（兑换 cashback、送 feedback、申请当 volunteer）时，请在 INSERT 之后加一行：
```php
notify_admins($conn, 'New cashback request', $name . ' redeemed RM2 Cashback.');
```
它会发给每一个 active 的 admin，admin 右上角的铃铛就会出现红点数字。
