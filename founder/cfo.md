# Unit economics: ArmorHub marketplace (platform's own take per paid order, SAR)

> **Currency note:** the tool prints `$`, but every figure below is in Saudi riyals (SAR).

## The CFO's note

**The margin.** On the current settings (10% commission, platform keeps 35% of it) the platform earns
SAR 31.50 on an average SAR 900 order. After costs that leaves SAR 4.65 per order (15%). At 60 orders a day the
margin is about 0%, and break-even is 61 orders a day. Year 1 loses SAR 47,595 on operations.

**The line to watch: the payment gateway fee.** At an estimated 2.75% + SAR 1 on a SAR 900 order it costs SAR 25.75,
which is 82% of the platform's SAR 31.50 take. If the platform pays it, nearly nothing is left. The commission split is the
second weak point: the platform gets only 3.5% of an order (10% x 35%); the rest goes to the company (25%) and the partner (40%).

**Cash.** About SAR 82,595 before it pays for itself on the current settings (SAR 35,000 startup plus year 1 losses).

**Three ways to improve the margin** (each is a what-if run on the same file):

| change | contribution per order | break-even a day | year 1 operating profit | cash needed |
| --- | ---: | ---: | ---: | ---: |
| Customer pays the gateway fee (it is added to the order) | SAR 30.40 | 10 | SAR 253,680 | SAR 38,940 |
| Commission 10% -> 15% (platform share unchanged) | SAR 20.40 | 14 | SAR 136,680 | SAR 42,820 |
| Platform share 35% -> 50% of the 10% commission | SAR 18.15 | 16 | SAR 110,355 | SAR 44,165 |

Both the first and the second together: contribution SAR 46.15, break-even 7 a day, year 1 operating profit SAR 437,955.
More volume alone does not fix it: at 90 orders a day the margin is only 5% and year 1 still loses SAR 20,392.

**The board's conditions about money:** no `founder/board.md` exists, so there are none to check yet.

**Not financial, tax or legal advice.** Revenue is before tax; Saudi Arabia and Egypt add VAT. An accountant should check the
commission structure, who legally holds customers' money, the payment-provider contract and tax before money moves.

**What is estimated, not known.** Order value, volume, fixed costs and startup spend are midpoints of ranges you chose
(see `cfo-sources.md`). The gateway fee is an assumed rate, not read from a contract. Replace them with real quotes.

Every number below comes from the input file. Nothing is looked up or guessed.

## One paid order

| line | per paid order |
| --- | ---: |
| Price | $31.50 |
| Payment gateway fee (2.75% of 900 + 1.00, estimate) | -$25.75 |
| Notifications, email and SMS (estimate) | -$0.10 |
| Support and disputes (estimate) | -$1.00 |
| **Contribution** (what each paid order leaves to pay the fixed costs) | **$4.65** (15%) |

## The margin that matters

Fixed costs: $8,500 a month (Hosting and database (estimate) $1,500, Part-time support and operations (estimate) $3,500, Marketing (estimate) $2,500, Software, email and SMS plans (estimate) $1,000).

- **Break-even: 61 paid orders a day.** Below that you lose money every month.
- **Profit margin at your plan** (60 a day): **-0%** of every sale, after every cost.
- Capacity: 100 a day.

## Year 1, month by month

| month | paid orders a day | revenue | profit | cumulative (after $35,000 startup) |
| ---: | ---: | ---: | ---: | ---: |
| 1 | 5 | $4,725 | -$7,802 | -$42,802 |
| 2 | 10 | $9,450 | -$7,105 | -$49,908 |
| 3 | 15 | $14,175 | -$6,408 | -$56,315 |
| 4 | 20 | $18,900 | -$5,710 | -$62,025 |
| 5 | 25 | $23,625 | -$5,012 | -$67,038 |
| 6 | 30 | $28,350 | -$4,315 | -$71,352 |
| 7 | 35 | $33,075 | -$3,618 | -$74,970 |
| 8 | 40 | $37,800 | -$2,920 | -$77,890 |
| 9 | 45 | $42,525 | -$2,222 | -$80,112 |
| 10 | 50 | $47,250 | -$1,525 | -$81,638 |
| 11 | 55 | $51,975 | -$828 | -$82,465 |
| 12 | 60 | $56,700 | -$130 | -$82,595 |

- **Year 1 operating profit: -$47,595** on $368,550 of revenue.
- After the $35,000 startup spend: -$82,595.
- Startup money earned back: not within year 1.
- Cash you need before it pays for itself: **$82,595**.

## What if

| scenario | margin at plan | break-even a day | year 1 profit |
| --- | ---: | ---: | ---: |
| Base plan | -0% | 61 | -$47,595 |
| Price -10% | -11% | 189 | -$84,450 |
| Volume -20% | -4% | 61 | -$58,476 |
| Unit costs +15% | -13% | 456 | -$94,717 |

## Red flags

- Year 1 loses money on operations (-$47,595).
- The startup spend is not earned back within year 1.
