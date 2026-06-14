<?php if (!defined('ABSPATH')) exit; ?>

<div class="status-filters count">
    <a href="<?php echo esc_url(remove_query_arg('filter_status')); ?>" class="<?php echo empty($filter_status) ? 'current' : ''; ?>">
        All (<?php echo intval($order_counts_raw->all_o ?? 0); ?>)
    </a> |
    <a href="<?php echo esc_url(add_query_arg('filter_status', 'wc-completed')); ?>" class="<?php echo $filter_status === 'wc-completed' ? 'current' : ''; ?>">
        Completed (<?php echo intval($order_counts_raw->comp ?? 0); ?>)
    </a> |
    <a href="<?php echo esc_url(add_query_arg('filter_status', 'wc-pending')); ?>" class="<?php echo $filter_status === 'wc-pending' ? 'current' : ''; ?>">
        Pending (<?php echo intval($order_counts_raw->pend ?? 0); ?>)
    </a> |
    <a href="<?php echo esc_url(add_query_arg('filter_status', 'wc-processing')); ?>" class="<?php echo $filter_status === 'wc-processing' ? 'current' : ''; ?>">
        Processing (<?php echo intval($order_counts_raw->proc ?? 0); ?>)
    </a> |
    <a href="<?php echo esc_url(add_query_arg('filter_status', 'wc-on-hold')); ?>" class="<?php echo $filter_status === 'wc-on-hold' ? 'current' : ''; ?>">
        On Hold (<?php echo intval($order_counts_raw->hold ?? 0); ?>)
    </a> |
    <a href="<?php echo esc_url(add_query_arg('filter_status', 'wc-cancelled')); ?>" class="<?php echo $filter_status === 'wc-cancelled' ? 'current' : ''; ?>">
        Cancelled (<?php echo intval($order_counts_raw->canc ?? 0); ?>)
    </a>
</div>

<form method="get" class="filters end-filters">
    <input type="hidden" name="section" value="<?php echo esc_attr($_GET['section'] ?? 'orders'); ?>">
    <select name="filter_month">
        <option value="">All Dates</option>
        <?php foreach(['01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'May','06'=>'Jun','07'=>'Jul','08'=>'Aug','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec'] as $m => $lab): ?>
            <option value="<?php echo $m; ?>" <?php selected($filter_month, $m); ?>><?php echo $lab; ?></option>
        <?php endforeach; ?>
    </select>
    <select name="filter_status">
        <option value="">Status</option>
        <option value="wc-completed" <?php selected($filter_status, 'wc-completed'); ?>>Completed</option>
        <option value="wc-pending" <?php selected($filter_status, 'wc-pending'); ?>>Pending</option>
        <option value="wc-processing" <?php selected($filter_status, 'wc-processing'); ?>>Processing</option>
    </select>
    <div class="input-icon">
        <input type="text" name="search_query" value="<?php echo esc_attr($search_query); ?>" placeholder="Search name...">
        <span class="icon"><i class="fa fa-search"></i></span>
    </div>
    <button type="submit" class="my-filters All-button">Apply</button>
    <a href="<?php echo esc_url(remove_query_arg(['filter_month', 'filter_status', 'search_query', 'paged'])); ?>&section=<?php echo esc_attr($_GET['section'] ?? 'orders'); ?>" class="button clear-filter">Clear</a>
</form>
