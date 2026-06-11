<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) return;

// -------------------------------------------------------
// Year selection
// -------------------------------------------------------
global $wpdb;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$available_years = array_filter( $wpdb->get_col( $wpdb->prepare(
	"SELECT DISTINCT YEAR(meta_value)
	 FROM {$wpdb->postmeta}
	 JOIN {$wpdb->posts} ON post_id = ID
	 WHERE meta_key = %s AND post_type = %s AND post_status != 'trash'
	 ORDER BY 1 DESC",
	'_ci_invoice_date',
	'ci_invoice'
) ) );
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

$current_year = intval( $_GET['ci_year'] ?? gmdate( 'Y' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only year filter for display.
if ( ! in_array( $current_year, $available_years, false ) && ! empty( $available_years ) ) {
	$current_year = (int) reset( $available_years );
}

// -------------------------------------------------------
// Pull all invoices for the selected year
// -------------------------------------------------------
$invoices = get_posts( [
	'post_type'      => 'ci_invoice',
	'posts_per_page' => -1,
	'post_status'    => [ 'publish', 'draft' ],
	'meta_query'     => [ [
		'key'     => '_ci_invoice_date',
		'value'   => [ $current_year . '-01-01', $current_year . '-12-31' ],
		'compare' => 'BETWEEN',
		'type'    => 'DATE',
	] ],
] );

// -------------------------------------------------------
// Crunch numbers
// -------------------------------------------------------
$total_invoiced    = 0.0;
$total_collected   = 0.0;
$total_outstanding = 0.0;
$monthly           = [];   // [1..12] => [invoiced, collected, outstanding, count]
$unpaid_list       = [];   // invoices with a remaining balance

foreach ( $invoices as $inv ) {
	$id       = $inv->ID;
	$total    = (float) get_post_meta( $id, '_ci_total',        true );
	$status   = get_post_meta( $id, '_ci_status',               true ) ?: 'draft';
	$inv_date = get_post_meta( $id, '_ci_invoice_date',         true );
	$due_date = get_post_meta( $id, '_ci_due_date',             true );
	$client   = (int) get_post_meta( $id, '_ci_client_id',      true );
	$payments = json_decode( get_post_meta( $id, '_ci_payments', true ) ?: '[]', true );

	$amt_paid = array_sum( array_column( $payments, 'amount' ) );
	$balance  = ( $status === 'paid' && empty( $payments ) ) ? 0.0 : max( 0.0, $total - $amt_paid );
	$collected = $total - $balance;

	$total_invoiced    += $total;
	$total_collected   += $collected;
	$total_outstanding += $balance;

	$month = $inv_date ? (int) wp_date( 'n', strtotime( $inv_date ) ) : 0;
	if ( $month >= 1 && $month <= 12 ) {
		$monthly[ $month ]['invoiced']    = ( $monthly[ $month ]['invoiced']    ?? 0.0 ) + $total;
		$monthly[ $month ]['collected']   = ( $monthly[ $month ]['collected']   ?? 0.0 ) + $collected;
		$monthly[ $month ]['outstanding'] = ( $monthly[ $month ]['outstanding'] ?? 0.0 ) + $balance;
		$monthly[ $month ]['count']       = ( $monthly[ $month ]['count']       ?? 0   ) + 1;
	}

	if ( $balance > 0 ) {
		$days_overdue = 0;
		if ( $due_date && strtotime( $due_date ) < strtotime( 'today' ) ) {
			$days_overdue = (int) floor( ( strtotime( 'today' ) - strtotime( $due_date ) ) / DAY_IN_SECONDS );
		}
		$unpaid_list[] = [
			'id'           => $id,
			'number'       => get_post_meta( $id, '_ci_invoice_number', true ),
			'client_name'  => $client ? get_the_title( $client ) : '—',
			'total'        => $total,
			'collected'    => $collected,
			'balance'      => $balance,
			'status'       => $status,
			'inv_date'     => $inv_date,
			'due_date'     => $due_date,
			'days_overdue' => $days_overdue,
		];
	}
}

// Sort unpaid by days overdue desc
usort( $unpaid_list, fn( $a, $b ) => $b['days_overdue'] <=> $a['days_overdue'] );

$invoice_count = count( $invoices );
$paid_count    = $invoice_count - count( $unpaid_list );

function ci_dash_money( float $n ): string {
	return '$' . number_format( $n, 2 );
}
function ci_dash_date( string $d ): string {
	return $d ? (string) wp_date( 'M j, Y', strtotime( $d ) ) : '—';
}

$month_names = [ 1 => 'January', 'February', 'March', 'April', 'May', 'June',
                 'July', 'August', 'September', 'October', 'November', 'December' ];
?>
<div class="wrap">
<h1>Sales &amp; Revenue — <?php echo esc_html( $current_year ); ?></h1>

<?php if ( ! empty( $available_years ) ) : ?>
<div class="ci-year-nav">
	<?php foreach ( $available_years as $y ) : ?>
		<a href="<?php echo esc_url( add_query_arg( 'ci_year', $y ) ); ?>"
		   class="ci-year-tab<?php echo (int) $y === $current_year ? ' active' : ''; ?>">
			<?php echo esc_html( $y ); ?>
		</a>
	<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ( empty( $invoices ) ) : ?>
<p style="color:#646970;margin-top:20px;">No invoices found for <?php echo esc_html( $current_year ); ?>.</p>
<?php else : ?>

<!-- Stat cards -->
<div class="ci-stat-cards">
	<div class="ci-stat-card">
		<div class="ci-stat-label">Total Invoiced</div>
		<div class="ci-stat-value"><?php echo esc_html( ci_dash_money( $total_invoiced ) ); ?></div>
		<div class="ci-stat-sub"><?php echo esc_html( $invoice_count ); ?> invoice<?php echo $invoice_count !== 1 ? 's' : ''; ?></div>
	</div>
	<div class="ci-stat-card ci-stat-card--green">
		<div class="ci-stat-label">Revenue Collected</div>
		<div class="ci-stat-value"><?php echo esc_html( ci_dash_money( $total_collected ) ); ?></div>
		<div class="ci-stat-sub"><?php echo esc_html( $paid_count ); ?> paid in full</div>
	</div>
	<div class="ci-stat-card<?php echo $total_outstanding > 0 ? ' ci-stat-card--amber' : ''; ?>">
		<div class="ci-stat-label">Outstanding Balance</div>
		<div class="ci-stat-value"><?php echo esc_html( ci_dash_money( $total_outstanding ) ); ?></div>
		<div class="ci-stat-sub"><?php echo esc_html( count( $unpaid_list ) ); ?> unpaid / partial</div>
	</div>
	<div class="ci-stat-card">
		<div class="ci-stat-label">Collection Rate</div>
		<div class="ci-stat-value"><?php echo $total_invoiced > 0 ? esc_html( number_format( ( $total_collected / $total_invoiced ) * 100, 1 ) ) . '%' : '—'; ?></div>
		<div class="ci-stat-sub">of total invoiced</div>
	</div>
</div>

<!-- Monthly breakdown -->
<div class="ci-dash-section">
	<h2>Monthly Breakdown</h2>
	<table class="ci-dash-table">
		<thead>
			<tr>
				<th>Month</th>
				<th class="num">Invoiced</th>
				<th class="num">Collected</th>
				<th class="num">Outstanding</th>
				<th class="num"># Invoices</th>
			</tr>
		</thead>
		<tbody>
			<?php
			$running_collected = 0.0;
			for ( $m = 1; $m <= 12; $m++ ) :
				if ( empty( $monthly[ $m ] ) ) continue;
				$row = $monthly[ $m ];
				$running_collected += $row['collected'];
			?>
			<tr>
				<td><?php echo esc_html( $month_names[ $m ] ); ?></td>
				<td class="num"><?php echo esc_html( ci_dash_money( $row['invoiced'] ) ); ?></td>
				<td class="num ci-collected"><?php echo esc_html( ci_dash_money( $row['collected'] ) ); ?></td>
				<td class="num<?php echo $row['outstanding'] > 0 ? ' ci-outstanding' : ''; ?>">
					<?php echo esc_html( $row['outstanding'] > 0 ? ci_dash_money( $row['outstanding'] ) : '—' ); ?>
				</td>
				<td class="num"><?php echo esc_html( $row['count'] ); ?></td>
			</tr>
			<?php endfor; ?>
		</tbody>
		<tfoot>
			<tr class="ci-dash-total-row">
				<td>Total</td>
				<td class="num"><?php echo esc_html( ci_dash_money( $total_invoiced ) ); ?></td>
				<td class="num ci-collected"><?php echo esc_html( ci_dash_money( $total_collected ) ); ?></td>
				<td class="num<?php echo $total_outstanding > 0 ? ' ci-outstanding' : ''; ?>">
					<?php echo esc_html( $total_outstanding > 0 ? ci_dash_money( $total_outstanding ) : '—' ); ?>
				</td>
				<td class="num"><?php echo esc_html( $invoice_count ); ?></td>
			</tr>
		</tfoot>
	</table>
</div>

<!-- Unpaid / partial invoices -->
<?php if ( ! empty( $unpaid_list ) ) : ?>
<div class="ci-dash-section">
	<h2>Unpaid &amp; Partial Invoices</h2>
	<table class="ci-dash-table">
		<thead>
			<tr>
				<th>Invoice #</th>
				<th>Client</th>
				<th>Status</th>
				<th class="num">Total</th>
				<th class="num">Collected</th>
				<th class="num">Balance Due</th>
				<th>Invoice Date</th>
				<th>Due Date</th>
				<th>Overdue</th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php
			$status_labels = [ 'draft' => 'Draft', 'sent' => 'Sent', 'partial' => 'Partial', 'overdue' => 'Overdue' ];
			foreach ( $unpaid_list as $row ) :
			?>
			<tr class="<?php echo $row['days_overdue'] > 0 ? 'ci-row-overdue' : ''; ?>">
				<td><a href="<?php echo esc_url( get_edit_post_link( $row['id'] ) ); ?>"><?php echo esc_html( $row['number'] ); ?></a></td>
				<td><?php echo esc_html( $row['client_name'] ); ?></td>
				<td><span class="ci-badge ci-badge--<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( $status_labels[ $row['status'] ] ?? $row['status'] ); ?></span></td>
				<td class="num"><?php echo esc_html( ci_dash_money( $row['total'] ) ); ?></td>
				<td class="num"><?php echo esc_html( $row['collected'] > 0 ? ci_dash_money( $row['collected'] ) : '—' ); ?></td>
				<td class="num ci-outstanding"><strong><?php echo esc_html( ci_dash_money( $row['balance'] ) ); ?></strong></td>
				<td><?php echo esc_html( ci_dash_date( $row['inv_date'] ) ); ?></td>
				<td><?php echo esc_html( ci_dash_date( $row['due_date'] ) ); ?></td>
				<td>
					<?php if ( $row['days_overdue'] > 0 ) : ?>
						<span class="ci-overdue-badge"><?php echo esc_html( $row['days_overdue'] ); ?>d</span>
					<?php else : ?>
						—
					<?php endif; ?>
				</td>
				<td>
					<a href="<?php echo esc_url( get_edit_post_link( $row['id'] ) ); ?>" class="button button-small">Edit</a>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php endif; ?>

<?php endif; ?>
</div><!-- .wrap -->

<style>
/* ---- Year nav ---- */
.ci-year-nav {
	display: flex;
	gap: 4px;
	margin: 16px 0 24px;
	flex-wrap: wrap;
}
.ci-year-tab {
	display: inline-block;
	padding: 6px 16px;
	border: 1px solid #c3c4c7;
	border-radius: 3px;
	background: #fff;
	color: #2c3338;
	text-decoration: none;
	font-size: 13px;
}
.ci-year-tab:hover { background: #f6f7f7; }
.ci-year-tab.active {
	background: #2271b1;
	border-color: #2271b1;
	color: #fff;
}

/* ---- Stat cards ---- */
.ci-stat-cards {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 16px;
	margin-bottom: 28px;
}
@media (max-width: 1100px) {
	.ci-stat-cards { grid-template-columns: repeat(2, 1fr); }
}
.ci-stat-card {
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	padding: 20px 22px;
}
.ci-stat-card--green { border-top: 3px solid #00a32a; }
.ci-stat-card--amber { border-top: 3px solid #dba617; }
.ci-stat-label {
	font-size: 11px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.05em;
	color: #646970;
	margin-bottom: 6px;
}
.ci-stat-value {
	font-size: 28px;
	font-weight: 700;
	color: #1e1e1e;
	line-height: 1.1;
	margin-bottom: 4px;
}
.ci-stat-sub {
	font-size: 12px;
	color: #646970;
}

/* ---- Section ---- */
.ci-dash-section {
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	padding: 20px 24px;
	margin-bottom: 24px;
	max-width: 1200px;
}
.ci-dash-section h2 {
	margin: 0 0 16px;
	padding-bottom: 10px;
	border-bottom: 1px solid #e0e0e0;
	font-size: 14px;
	font-weight: 600;
}

/* ---- Table ---- */
.ci-dash-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 13px;
}
.ci-dash-table th {
	background: #f6f7f7;
	border-bottom: 2px solid #c3c4c7;
	padding: 7px 10px;
	text-align: left;
	font-size: 11px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	color: #50575e;
}
.ci-dash-table th.num,
.ci-dash-table td.num { text-align: right; }
.ci-dash-table tbody td {
	padding: 8px 10px;
	border-bottom: 1px solid #f0f0f0;
	vertical-align: middle;
}
.ci-dash-table tfoot td {
	padding: 10px 10px;
	border-top: 2px solid #c3c4c7;
	font-weight: 700;
}
.ci-dash-table tbody tr:hover { background: #f9f9f9; }

.ci-collected   { color: #00a32a; }
.ci-outstanding { color: #b45309; }

.ci-row-overdue td { background: #fff8f0; }
.ci-row-overdue:hover td { background: #fff2e0 !important; }

.ci-overdue-badge {
	display: inline-block;
	background: #fee2e2;
	color: #991b1b;
	font-size: 11px;
	font-weight: 700;
	padding: 1px 6px;
	border-radius: 10px;
}

.ci-dash-total-row td { font-size: 13px; }
</style>
