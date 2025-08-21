<?php
// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

// Save custom CSS if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bmw_custom_css'])) {
    check_admin_referer('bmw_save_custom_css', 'bmw_custom_css_nonce');
    $custom_css = wp_kses_post($_POST['bmw_custom_css']); // Use wp_kses_post for safe saving
    update_option('bmw_custom_css', $custom_css);
    $saved = true;
}

// Retrieve the saved custom CSS
$custom_css = get_option('bmw_custom_css', '');
?>
<div class="wrap">
    <h1><?php echo __('BMW Club Members - CSS Editor', 'bmw-clubs-africa'); ?></h1>
    <p><?php echo __('Manage custom CSS styles for the BMW Clubs plugin.', 'bmw-clubs-africa'); ?></p>

    <?php if (!empty($saved)) : ?>
        <div class="updated notice is-dismissible">
            <p><?php echo __('Custom CSS saved successfully!', 'bmw-clubs-africa'); ?></p>
        </div>
    <?php endif; ?>

    <div class="bmw-css-editor">
        <form method="POST">
            <?php wp_nonce_field('bmw_save_custom_css', 'bmw_custom_css_nonce'); ?>
            <textarea id="bmw_custom_css" name="bmw_custom_css" rows="15" style="width: 60%;height: 600px;"><?php echo esc_textarea($custom_css); ?></textarea>
            <p>
                <button type="submit" class="button button-primary"><?php echo __('Save CSS', 'bmw-clubs-africa'); ?></button>
            </p>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const textarea = document.getElementById('bmw_custom_css');

    textarea.addEventListener('input', function (event) {
        const cursorPos = this.selectionStart;
        const textBefore = this.value.substring(0, cursorPos);
        const textAfter = this.value.substring(cursorPos);
        
        // Auto-closing for `{`
        if (event.inputType === "insertText" && event.data === "{") {
            this.value = textBefore + "}" + textAfter;
            this.selectionStart = this.selectionEnd = cursorPos; // Move cursor back
        }

        // Auto-closing for `<` tags
        if (event.inputType === "insertText" && event.data === "<") {
            const tagMatch = textBefore.match(/<([\w-]*)$/); // Matches a tag name
            if (tagMatch) {
                const tagName = tagMatch[1];
                this.value = textBefore + ">" + `</${tagName}>` + textAfter;
                this.selectionStart = this.selectionEnd = cursorPos + 1; // Move cursor inside the opening tag
            }
        }
    });

    textarea.addEventListener('keydown', function (event) {
        // Pressing Enter auto-indents inside braces
        if (event.key === "Enter") {
            const cursorPos = this.selectionStart;
            const textBefore = this.value.substring(0, cursorPos);
            const textAfter = this.value.substring(cursorPos);
            const lastBraceIndex = textBefore.lastIndexOf("{");

            if (lastBraceIndex !== -1 && !textBefore.includes("}", lastBraceIndex)) {
                event.preventDefault();
                const indent = "    "; // 4 spaces
                this.value = textBefore + "\n" + indent + "\n" + textAfter;
                this.selectionStart = this.selectionEnd = cursorPos + indent.length + 1;
            }
        }
    });
});
</script>
