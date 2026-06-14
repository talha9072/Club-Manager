<?php if (!defined('ABSPATH')) exit; ?>
<?php if ($total_pages > 1) : ?>
<div class="pagination" style="text-align: center; display: flex; flex-wrap: wrap; justify-content: center; gap: 5px; margin-top: 20px;">
    <?php if ($current_page > 1): ?>
        <a href="<?php echo esc_url(add_query_arg('paged', $current_page - 1)); ?>" class="prev" style="padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; color: #333;">Previous</a>
    <?php endif; ?>

    <?php
    $start_p = max(1, $current_page - 2);
    $end_p = min($total_pages, $start_p + 4);
    if ($start_p > 1) echo '<span style="padding: 5px;">...</span>';
    for ($i = $start_p; $i <= $end_p; $i++): ?>
        <a href="<?php echo esc_url(add_query_arg('paged', $i)); ?>" 
           style="padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; <?php echo ($current_page == $i) ? 'background: #10487B; color: #fff;' : 'color: #333;'; ?>">
            <?php echo $i; ?>
        </a>
    <?php endfor; 
    if ($end_p < $total_pages) echo '<span style="padding: 5px;">...</span>';
    ?>

    <?php if ($current_page < $total_pages): ?>
        <a href="<?php echo esc_url(add_query_arg('paged', $current_page + 1)); ?>" class="next" style="padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; color: #333;">Next</a>
    <?php endif; ?>
</div>
<?php endif; ?>
