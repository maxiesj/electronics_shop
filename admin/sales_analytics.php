<?php
require_once dirname(__FILE__) . '/../session_auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
include_once dirname(__FILE__) . '/../db.php';
if (!verifyWorkspaceClearance('sales_analytics.php')) {
    header('Location: ../login.php');
    exit;
}
date_default_timezone_set('Africa/Nairobi');
$default_from = date('Y-m-01');
$default_to = date('Y-m-d');
$from = (string)($_GET['date_from'] ?? $default_from);
$to = (string)($_GET['date_to'] ?? $default_to);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from=$default_from;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to=$default_to;
if ($from > $to) [$from,$to]=[$to,$from];
$rollup = 'LEFT JOIN (SELECT order_id,SUM(amount) paid,MAX(created_at) settled_at FROM payments WHERE LOWER(payment_status)=\'completed\'';
$rollup .= ' GROUP BY order_id) pay ON pay.order_id=o.id';
$eligible = 'COALESCE(pay.paid,0)+0.01>=o.total_amount';
$eligible .= ' AND LOWER(o.order_status)<>\'cancelled\'';
$eligible .= ' AND DATE(pay.settled_at) BETWEEN ' . chr(63) . ' AND ' . chr(63);
$eligible .= ' AND NOT EXISTS(SELECT 1 FROM payments r WHERE r.order_id=o.id AND LOWER(r.payment_status)=\'refunded\')';
$profitRollup = ' LEFT JOIN (SELECT order_id,COUNT(*) line_count,SUM(CASE WHEN unit_cost IS NULL OR unit_cost<=0 THEN 1 ELSE 0 END) missing_cost_lines,SUM(quantity*unit_cost) cogs FROM order_items GROUP BY order_id) pf ON pf.order_id=o.id';
$profitReady = 'COALESCE(pf.line_count,0)>0 AND COALESCE(pf.missing_cost_lines,0)=0';
$profitSales = 'CASE WHEN ' . $profitReady . ' THEN o.net_amount ELSE 0 END';
$profitCost = 'CASE WHEN ' . $profitReady . ' THEN pf.cogs ELSE 0 END';
$sql = 'SELECT COUNT(*) c,COALESCE(SUM(o.total_amount),0) g';
$sql .= ',COALESCE(SUM(o.net_amount),0) n,COALESCE(SUM(o.vat_amount),0) v';
$sql .= ',COALESCE(SUM(CASE WHEN ' . $profitReady . ' THEN 1 ELSE 0 END),0) pc';
$sql .= ',COALESCE(SUM(' . $profitSales . '),0) pn';
$sql .= ',COALESCE(SUM(' . $profitCost . '),0) cost';
$sql .= ',COALESCE(SUM((' . $profitSales . ')-(' . $profitCost . ')),0) profit';
$sql .= ' FROM orders o ' . $rollup . $profitRollup . ' WHERE ' . $eligible;
$stmt = $conn->prepare($sql);
$stmt->bind_param('ss',$from,$to);
$stmt->execute();
$totals = $stmt->get_result()->fetch_assoc();
$stmt->close();
$count=(int)$totals['c']; $gross=(float)$totals['g'];
$net=(float)$totals['n']; $vat=(float)$totals['v'];
$profitCount=(int)$totals['pc'];
$profitNet=(float)$totals['pn'];
$recordedCost=(float)$totals['cost'];
$grossProfit=(float)$totals['profit'];
$profitMargin=$profitNet>0?($grossProfit/$profitNet)*100:null;
$missingProfitCount=max(0,$count-$profitCount);
$operatingExpenses=0.0;
$payrollExpenses=0.0;
$stmt=$conn->prepare("SELECT COALESCE(SUM(amount),0) total FROM operating_expenses WHERE status='active' AND expense_date BETWEEN ? AND ?");
$stmt->bind_param('ss',$from,$to);
$stmt->execute();
$row=$stmt->get_result()->fetch_assoc();
$operatingExpenses=(float)($row['total']??0);
$stmt->close();
$stmt=$conn->prepare("SELECT COALESCE(SUM(gross_pay),0) total FROM payroll_records WHERE status='paid' AND payment_date BETWEEN ? AND ?");
$stmt->bind_param('ss',$from,$to);
$stmt->execute();
$row=$stmt->get_result()->fetch_assoc();
$payrollExpenses=(float)($row['total']??0);
$stmt->close();
$totalExpenses=$operatingExpenses+$payrollExpenses;
$profitComplete=$missingProfitCount===0;
$netProfit=$profitComplete?$grossProfit-$totalExpenses:null;
$expenseBreakdown=[];
$stmt=$conn->prepare("SELECT category,SUM(amount) total FROM operating_expenses WHERE status='active' AND expense_date BETWEEN ? AND ? GROUP BY category ORDER BY total DESC");
$stmt->bind_param('ss',$from,$to);
$stmt->execute();
$result=$stmt->get_result();
$operational=['pending'=>['count'=>0,'value'=>0.0],'cancelled'=>['count'=>0,'value'=>0.0]];
$sql = 'SELECT LOWER(order_status) status,COUNT(*) c';
$sql .= ',COALESCE(SUM(total_amount),0) value FROM orders';
$sql .= ' WHERE DATE(created_at) BETWEEN ' . chr(63) . ' AND ' . chr(63);
$sql .= ' AND LOWER(order_status) IN (\'pending\',\'cancelled\')';
$sql .= ' GROUP BY LOWER(order_status)';
$stmt = $conn->prepare($sql);
$stmt->bind_param('ss',$from,$to);
$stmt->execute();
$status_result=$stmt->get_result();
while($row=$status_result->fetch_assoc()) {
    $operational[$row['status']]=['count'=>(int)$row['c'],'value'=>(float)$row['value']];
}
$stmt->close();
$daily = [];
$sql = 'SELECT DATE(pay.settled_at) day,COUNT(*) c';
$sql .= ',SUM(o.total_amount) g,SUM(o.net_amount) n,SUM(o.vat_amount) v';
$sql .= ',SUM(CASE WHEN ' . $profitReady . ' THEN 1 ELSE 0 END) pc';
$sql .= ',SUM(' . $profitCost . ') cost';
$sql .= ',SUM((' . $profitSales . ')-(' . $profitCost . ')) profit';
$sql .= ' FROM orders o ' . $rollup . $profitRollup . ' WHERE ' . $eligible;
$sql .= ' GROUP BY DATE(pay.settled_at) ORDER BY day DESC';
$stmt = $conn->prepare($sql);
$stmt->bind_param('ss',$from,$to);
$stmt->execute();
$result=$stmt->get_result();
while ($row=$result->fetch_assoc()) $daily[]=$row;
$stmt->close();
?>
<style>
.analytics{font-family:system-ui;color:#172033}.analytics-head{display:flex;justify-content:space-between;gap:18px;align-items:end}.analytics-head p,.analytics-note{color:#64748b}.analytics-filter{display:flex;gap:9px;align-items:end}.analytics-filter label{font-size:11px;font-weight:800;color:#64748b}.analytics-filter input{display:block;padding:9px;border:1px solid #cbd5e1;border-radius:8px}.analytics-filter button{padding:10px 14px;border:0;border-radius:8px;background:#2563eb;color:white;font-weight:800}.analytics-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:20px 0}.analytics-card{padding:17px;border:1px solid #bfdbfe;background:#eff6ff;border-radius:12px}.analytics-card:nth-child(2){background:#ecfdf5;border-color:#a7f3d0}.analytics-card:nth-child(3){background:#fff7ed;border-color:#fed7aa}.analytics-card:nth-child(4){background:#f5f3ff;border-color:#ddd6fe}.analytics-card small,.analytics-card strong{display:block}.analytics-card strong{font-size:20px;margin-top:7px}.analytics-table-wrap{overflow:auto;border:1px solid #e2e8f0;border-radius:12px}.analytics-table{width:100%;border-collapse:collapse;min-width:700px}.analytics-table th,.analytics-table td{padding:12px;border-bottom:1px solid #e2e8f0;text-align:left}.analytics-table th{background:#f8fafc;color:#64748b;font-size:11px;text-transform:uppercase}.money{white-space:nowrap;font-weight:700}.analytics-note{font-size:12px;margin-top:12px}@media(max-width:850px){.analytics-head{align-items:stretch;flex-direction:column}.analytics-filter{flex-wrap:wrap}.analytics-cards{grid-template-columns:1fr 1fr}}@media(max-width:520px){.analytics-cards{grid-template-columns:1fr}}
.operational-cards{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:0 0 20px}.operational-card{padding:17px;border-radius:12px;background:#fffbeb;border:1px solid #fde68a}.operational-card.cancelled{background:#fef2f2;border-color:#fecaca}.operational-card strong,.operational-card span{display:block}.operational-card strong{font-size:20px;margin:6px 0}.operational-card small{color:#64748b}@media(max-width:520px){.operational-cards{grid-template-columns:1fr}}
.profit-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:0 0 20px}.profit-card{padding:17px;border:1px solid #a7f3d0;background:#ecfdf5;border-radius:12px}.profit-card small,.profit-card strong{display:block}.profit-card strong{font-size:20px;margin-top:7px}.profit-alert{margin:0 0 16px;padding:13px 15px;border:1px solid #fed7aa;border-radius:10px;background:#fff7ed;color:#9a3412}.profit-alert.ready{border-color:#a7f3d0;background:#ecfdf5;color:#047857}.profit-unavailable{color:#9a3412;font-weight:800}
@media(max-width:850px){.profit-cards{grid-template-columns:1fr 1fr}}@media(max-width:520px){.profit-cards{grid-template-columns:1fr}}
.net-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:0 0 20px}.net-card{padding:17px;border:1px solid #ddd6fe;background:#f5f3ff;border-radius:12px}.net-card small,.net-card strong{display:block}.net-card strong{font-size:20px;margin-top:7px}.net-card.result{background:#eff6ff;border-color:#bfdbfe}.net-card.result.loss{background:#fef2f2;border-color:#fecaca;color:#b42318}.expense-summary{display:flex;gap:8px;flex-wrap:wrap;margin:-8px 0 18px}.expense-chip{padding:7px 10px;border-radius:999px;background:#f8fafc;border:1px solid #e2e8f0;color:#475569;font-size:12px}
@media(max-width:850px){.net-cards{grid-template-columns:1fr 1fr}}@media(max-width:520px){.net-cards{grid-template-columns:1fr}}
</style>
<section class='analytics'>
<header class='analytics-head'><div><h2>Financial Analytics</h2><p>Fully paid, non-cancelled and non-refunded orders grouped by settlement date.</p></div><form class='analytics-filter' method='get' action='sales_analytics.php'><label>From<input type='date' name='date_from' value='<?=htmlspecialchars($from)?>'></label><label>To<input type='date' name='date_to' value='<?=htmlspecialchars($to)?>'></label><button type='submit'>Apply period</button></form></header>
<div class='analytics-cards'><article class='analytics-card'><small>Settled orders</small><strong><?=number_format($count)?></strong></article><article class='analytics-card'><small>Gross settled sales</small><strong>KES <?=number_format($gross,2)?></strong></article><article class='analytics-card'><small>Stored VAT component</small><strong>KES <?=number_format($vat,2)?></strong></article><article class='analytics-card'><small>Net settled sales</small><strong>KES <?=number_format($net,2)?></strong></article></div>
<h3>Order status summary</h3><div class='operational-cards'><article class='operational-card'><span>Pending orders created in period</span><strong><?=number_format($operational['pending']['count'])?></strong><small>Gross order value: KES <?=number_format($operational['pending']['value'],2)?> — not recognized as settled revenue</small></article><article class='operational-card cancelled'><span>Cancelled orders created in period</span><strong><?=number_format($operational['cancelled']['count'])?></strong><small>Gross cancelled value: KES <?=number_format($operational['cancelled']['value'],2)?> — excluded from revenue</small></article></div>
<h3>Gross profit</h3>
<div class="profit-cards"><article class="profit-card"><small>Profit-ready settled orders</small><strong><?=number_format($profitCount)?> / <?=number_format($count)?></strong></article><article class="profit-card"><small>Profit-ready net sales</small><strong><?=$profitCount>0?'KES '.number_format($profitNet,2):'Unavailable'?></strong></article><article class="profit-card"><small>Recorded cost of goods</small><strong><?=$profitCount>0?'KES '.number_format($recordedCost,2):'Unavailable'?></strong></article><article class="profit-card"><small>Gross profit</small><strong><?=$profitCount>0?'KES '.number_format($grossProfit,2):'Unavailable'?></strong><small><?=$profitMargin!==null?number_format($profitMargin,2).'% margin':'Waiting for complete costs'?></small></article></div>
<?php if($missingProfitCount>0):?><div class="profit-alert"><strong>Profit is incomplete for this period.</strong> <?=number_format($missingProfitCount)?> settled order(s) have one or more products without a frozen buying cost. Those orders are excluded from profit instead of being treated as 100% profit.</div><?php elseif($count>0):?><div class="profit-alert ready">All settled orders in this period have complete recorded costs.</div><?php endif;?>
<h3>Daily settlements</h3><div class='analytics-table-wrap'><table class='analytics-table'><thead><tr><th>Settlement date</th><th>Settled orders</th><th>Gross sales</th><th>Net sales</th><th>Stored VAT</th><th>Profit coverage</th><th>Recorded cost</th><th>Gross profit</th></tr></thead><tbody>
<?php if ($daily): foreach ($daily as $row): ?><tr><td><strong><?=date('d M Y',strtotime($row['day']))?></strong></td><td><?=(int)$row['c']?></td><td class='money'>KES <?=number_format((float)$row['g'],2)?></td><td class='money'>KES <?=number_format((float)$row['n'],2)?></td><td class='money'>KES <?=number_format((float)$row['v'],2)?></td><td><?=number_format((int)$row['pc'])?> / <?=number_format((int)$row['c'])?></td><td class='money'><?=(int)$row['pc']>0?'KES '.number_format((float)$row['cost'],2):'<span class="profit-unavailable">Unavailable</span>'?></td><td class='money'><?=(int)$row['pc']>0?'KES '.number_format((float)$row['profit'],2):'<span class="profit-unavailable">Unavailable</span>'?></td></tr><?php endforeach; else: ?><tr><td colspan='8' style='text-align:center;padding:30px;color:#64748b'>No eligible settled orders were found for this period.</td></tr><?php endif; ?>
</tbody></table></div>
<h3>Net profit after expenses</h3>
<div class="net-cards"><article class="net-card"><small>Non-salary operating expenses</small><strong>KES <?=number_format($operatingExpenses,2)?></strong></article><article class="net-card"><small>Paid gross payroll</small><strong>KES <?=number_format($payrollExpenses,2)?></strong></article><article class="net-card"><small>Total expenses</small><strong>KES <?=number_format($totalExpenses,2)?></strong></article><article class="net-card result <?=($netProfit!==null&&$netProfit<0)?'loss':''?>"><small>Net profit / loss</small><strong><?=$netProfit===null?'Unavailable':'KES '.number_format($netProfit,2)?></strong></article></div>
<?php if($expenseBreakdown):?><div class="expense-summary"><?php foreach($expenseBreakdown as $expense):?><span class="expense-chip"><?=htmlspecialchars($expense['category'])?>: KES <?=number_format((float)$expense['total'],2)?></span><?php endforeach;?></div><?php endif;?>
<p class='analytics-note'>Totals exclude unpaid, partially paid, cancelled, and refunded orders. “Stored VAT” is the VAT captured on eligible orders; this dashboard does not confirm a tax filing or payment to KRA.</p>
<p class='analytics-note'>Gross profit is profit-ready net sales after VAT minus frozen unit buying costs. It is before salaries, rent, delivery and other operating expenses.</p>
<p class='analytics-note'>Net profit subtracts active operating expenses and paid gross payroll by payment date. Draft or voided payroll and voided expenses are excluded.</p>
</section>
