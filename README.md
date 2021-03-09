# Stock Limits
A stock share limit order generator. Input your current positions and based on minimum and maximum price targets, generate increasingly higher limit orders to maximize profits.

## Installation (MacOs)
```
$ cd ~
$ git clone git@github.com:ajthenewguy/stock-limits.git
$ cd stock-limits
$ composer install
$ ln -s ./exe.php ~/stock-limits
```

## Usage
```
$ stock-limits

```

The above instructions assume you link the executable (exe.php) to a location in your path (~/stock-limits). The name of the executable is up to you, but creating a symbolic link (the "ln" command in the Installation instructions above) creates an alias to the executable.

Note: at the main menu type "exit" to quit the script. All entered data will be lost.