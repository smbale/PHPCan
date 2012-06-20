<?php
/**
* phpCan - http://idc.anavallasuiza.com/
*
* phpCan is released under the GNU Affero GPL version 3
*
* More information at license.txt
*/

defined('ANS') or die();

$info['id_field'] = uniqid();
?>

<div class="format format-<?php echo $info['format']; ?>">
    <label class="field" for="<?php echo $info['id_field']; ?>">
        <strong data-name="<?php echo $info['field']; ?>" data-table="<?php echo $info['table']; ?>" data-language="<?php echo $info['language']; ?>" title="<?php __e('Double click to show/hide'); ?>">
            <?php echo $info['title']; ?>
        </strong>

        <?php
        if (hasText($info['description'])) {
            echo '<p>'.$info['description'].'</p>';
        }
        ?>
    </label>

    <div>
        <?php $Templates->render($template_content, array('info' => $info)); ?>
    </div>
</div>
