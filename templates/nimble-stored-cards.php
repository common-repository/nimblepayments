<ul>
    <?php foreach ($cards as $card_number => $card) : ?>
    <li>
        <input class="input-radio" type="radio" id="<?php echo $this->id; ?>_storedcard_<?php echo $card_number; ?>" name="<?php echo $this->id; ?>_storedcard" <?php if ($card['default']): echo "checked"; endif; ?> value="<?php echo base64_encode(json_encode($card)); ?>" />
        <label for="<?php echo $this->id; ?>_storedcard_<?php echo $card_number; ?>" class="stored_card <?php echo strtolower($card['cardBrand']);?>"><?php echo $card['maskedPan'];?></label>
    </li>
    <?php endforeach;?>
    <li>
        <input class="input-radio" type="radio" id="<?php echo $this->id; ?>_storedcard_new" name="<?php echo $this->id; ?>_storedcard" value="" />
        <label for="<?php echo $this->id; ?>_storedcard_new" class="stored_card"><?php _e('New card', 'woocommerce-nimble-payments');?></label>
    </li>
</ul>