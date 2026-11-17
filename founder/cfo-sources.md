# Where each number comes from

| input | value | source |
| --- | --- | --- |
| Commission | 10% of the order | `apps/api/config/platform.php` (`commission_percentage`), editable in admin settings |
| Commission split | platform 35%, company 25%, partner 40% | `apps/api/config/platform.php` (`commission_split`) |
| Average order | SAR 900 | Estimate: midpoint of the 300 to 1,500 range you chose |
| Platform price per order | SAR 31.50 | Calculation: 900 x 10% x 35% |
| Volume at plan | 60 orders a day | Estimate: midpoint of the 20 to 100 range you chose; capacity set at 100 |
| Ramp | 5 to 60 a day, +5 each month | Estimate. No buyer panel or real orders to check it against |
| Fixed costs | SAR 8,500 a month | Estimate: midpoint of 2,000 to 15,000. The split (hosting 1,500, support 3,500, marketing 2,500, software 1,000) is mine |
| Startup | SAR 35,000 | Estimate: midpoint of 10,000 to 60,000. The split is mine |
| Payment gateway fee | 2.75% + SAR 1 per order | Estimate of a typical card rate. Not verified: the app ships with a simulated gateway only (`config/platform.php`); replace it with your provider's published rate |
| Notifications, support | SAR 0.10 and SAR 1.00 per order | Estimates |
