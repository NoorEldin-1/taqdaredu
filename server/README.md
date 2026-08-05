# ملفّات الخادم خارج جذر الويب

جذر هذا المستودع مرآةٌ لـ `/home/taqdaredu.com/public_html`. الملفّات هنا
تعيش **فوقه** في `/home/taqdaredu.com/`، فلا تُنسَخ مع بقيّة الشجرة ولولا
حفظها هنا لضاعت مع أوّل إعادة تنصيب.

| الملفّ | موضعه على الخادم | ما هو |
|---|---|---|
| `cron_taqdar.php` | `/home/taqdaredu.com/cron_taqdar.php` | مُقلِع CLI: يضبط `HTTP_HOST` قبل تحميل CodeIgniter، لأنّ `config.php` يشتقّ `base_url` منه وهو غائب في سطر الأوامر. خارج جذر الويب عمدًا. |
| `crontab.taqda9296` | `crontab -l -u taqda9296` | المهامّ الدورية. تُنفَّذ بصلاحية مالك ملفّات الموقع لا الـroot، وبمفسّر LiteSpeed `lsphp82` نفسه الذي يخدم الويب. |

هذه نسخٌ مرجعيّة: تعديلها هنا لا يغيّر شيئًا على الخادم. للتطبيق:

```bash
scp server/cron_taqdar.php taqda9296@88.222.221.162:~/cron_taqdar.php
ssh taqda9296@88.222.221.162 'crontab -' < server/crontab.taqda9296
```
