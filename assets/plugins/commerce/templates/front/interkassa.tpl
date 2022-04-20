<form action="<?= $url ?>" method="<?= !empty($method) ? $method : 'get' ?>" id="payment_request" style="display: none;">
    <?php foreach ($data as $key => $value): ?>
        <?php if (is_array($value)): ?>
            <?php if ($key == 'ik_products'): ?>
                <?php foreach ($value as $i => $product): ?>
                    <?php foreach ($product as $name => $field): ?>
                        <input type="hidden" name="<?= $key ?>[<?= $i ?>][<?= $name ?>]" value="<?= htmlentities($field) ?>">
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <?php foreach ($value as $name => $field): ?>
                    <input type="hidden" name="<?= $key ?>[<?= $name ?>]" value="<?= htmlentities($field) ?>">
                <?php endforeach; ?>
            <?php endif; ?>
        <?php else: ?>
            <input type="hidden" name="<?= $key ?>" value="<?= htmlentities($value) ?>">
        <?php endif; ?>
    <?php endforeach; ?>
</form>

<script type="text/javascript">
    <?php if (ci()->commerce->getSetting('instant_redirect_to_payment') == 1 || !ci()->commerce->loadProcessor()->isOrderStarted()): ?>
        document.getElementById('payment_request').submit();
    <?php endif; ?>
</script>
