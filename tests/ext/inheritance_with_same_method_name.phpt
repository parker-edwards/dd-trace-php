--TEST--
Ensure the `parent::` method is invoked from a sub class
--DESCRIPTION--
This bug was found from the Drupal 7 DBAL:
https://github.com/drupal/drupal/blob/bc60c9298a6b1a09c22bea7f5d87916902c27024/includes/database/sqlite/database.inc#L238
--FILE--
<?php

class Foo
{
    public function doStuff()
    {
        return 42;
    }
}

class Bar extends Foo
{
    public function doStuff()
    {
        return 1337;
    }

    public function parentDoStuff()
    {
        # Should return "42"
        return parent::doStuff();
    }

    public function myDoStuff()
    {
        # Should return "1337"
        return $this->doStuff();
    }
}

$bar = new Bar;
echo "Before tracing:\n";
dd_trace_noop();
echo $bar->parentDoStuff() . "\n";
echo $bar->myDoStuff() . "\n";

dd_trace('Foo', 'doStuff', function () {
    var_dump(dd_trace_invoke_original());
    var_dump(get_called_class());
    #return call_user_func_array([get_called_class(), 'doStuff'], func_get_args());
    return call_user_func_array([$this, 'doStuff'], func_get_args());
});

dd_trace('Bar', 'parentDoStuff', function () {
    var_dump(get_called_class());
    return call_user_func_array([$this, 'parentDoStuff'], func_get_args());
});

echo "After tracing:\n";
dd_trace_noop();
echo $bar->parentDoStuff() . "\n";
echo $bar->myDoStuff() . "\n";
?>
--EXPECT--
Before tracing:
42
1337
After tracing:
42
1337
