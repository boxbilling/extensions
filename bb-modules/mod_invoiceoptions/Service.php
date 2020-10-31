<?php

class Box_Mod_Invoiceoptions_Service
{
    public static function onBeforeClientInvoiceDelete(Box_Event $event)
    {
        throw new Box_Exception('Invoice can not be removed.');
    }
}