<?php

class LaybuyHelper
{
    static public function makeUniqueReference($id) {
        return '#' . uniqid() . $id . time();
    }
}
