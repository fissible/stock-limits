#!/usr/bin/env php
<?php
error_reporting(E_ALL);
if (version_compare(phpversion(), '7.4.0', '<')) {
    print "ERROR: PHP version must be greater than or equal to 7.4.0";
    exit(1);
}
if (version_compare(phpversion(), '7.9.9', '>')) {
    print "ERROR: PHP version must be less than or equal to 7.9.9";
    exit(1);
}

require_once(__DIR__ . '/vendor/autoload.php');

$error = null;

pcntl_async_signals(true);
pcntl_signal(SIGINT, 'quit');
pcntl_signal(SIGINT, 'quit');
register_shutdown_function('cleanup');
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (0 === error_reporting()) {
        return false;
    }
    throw new \Exception($errstr.' in '.$errfile.'['.$errline.']');
});
function cleanup()
{
    global $app, $error;
    if ($app->screen) {
        system('tput rmcup');
    }

    if (!is_null($error)) {
        print $error."\n";
    }
}
function quit(int $code = 0)
{
    exit($code);
}

final class Account {

    private $name;

    private $shares;

    private $cost;

    private $formatter;

    public function __construct(string $name, float $shares, float $cost)
    {
        $this->setName($name);
        $this->setShares($shares);
        $this->setCost($cost);
        $this->formatter = new \NumberFormatter('en_US', \NumberFormatter::CURRENCY);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function setName(string $name)
    {
        $this->name = $name;
    }

    public function shares(): float
    {
        return $this->shares;
    }

    public function setShares(float $shares)
    {
        $this->shares = $shares;
    }

    public function cost(): float
    {
        return $this->cost;
    }

    public function setCost(float $cost)
    {
        $this->cost = $cost;
    }

    public function totalCost()
    {
        return $this->cost * $this->shares;
    }

    private function moneyFormat(float $number)
    {
        return $this->formatter->formatCurrency($number, 'USD');
    }

    public function __get(string $name)
    {
        $value = null;

        if (isset($this->$name)) {
            $value = $this->$name;
        } elseif (method_exists($this, $name)) {
            $value = call_user_func_array([$this, $name]);
        }
        
        if (in_array($name, ['cost', 'totalCost'])) {
            $value = $this->moneyFormat($value);
        }
        return $value;
    }
}

/**
 * APPLICATION
 */
final class SellLimits extends PhpCli\Application
{
    private $Accounts;

    private $formatter;

    private $minPrice;

    private $maxPrice;
    
    public function calculate()
    {
        if (!isset($this->maxPrice)) {
            $this->line('No maximum share price configured.');
            return null;
        }

        if (!isset($this->Accounts)) {
            $this->line('No accounts configured.');
            return null;
        }

        $xPos = 0;

        $this->clear();

        $this->Accounts->each(function (Account $Account) use (&$xPos) {
            // system('tput cup 0 '.$xPos);

            $out = [
                'sells' => []
            ];
            $sum = 0.00;
            $shares = $Account->shares();
            $orderSize = max(min(floor($shares / 10), $shares), 5);

            $minimum = ceil($Account->totalCost() / $orderSize);
            if (isset($this->minPrice)) {
                $minimum = max($minimum, $this->minPrice);
            }

            // Get the range and divide it by the number of 5-share chunks in the account
            $increment = floor(($this->maxPrice - $minimum) / ($Account->shares() / $orderSize));

            // Round odd numbers up, ie. 8181 -> 8000
            $increment = round($increment, -1 * (floor(log($increment) / 2) - 1));
            
            /*
                1 share
                    sell 1 @ $Account->cost() / $Account->shares()

                2 shares
                    sell 2 @ $Account->cost() / $Account->shares()
                
                3 shares
                    sell 1 @ $Account->cost() / 1
                    sell 2 @ $Account->cost() / 2
            */

            $price = $minimum;
            while ($shares > $orderSize) {
                // $out['sells'][] = [
                //     'shares' => 5,
                //     'price' => $this->moneyFormat($price)
                // ];
                $out['sells'][] = [
                    $orderSize, $this->moneyFormat($price)
                ];
                $sum += $orderSize * $price;
                $price += $increment;
                $shares -= $orderSize;
            }
            if ($shares > 0) {
                // $out['sells'][] = [
                //     'shares' => $shares,
                //     'price' => $this->moneyFormat($price)
                // ];
                $out['sells'][] = [
                    $shares, $this->moneyFormat($price)
                ];
                $sum += $shares * $price;
            }

            $this->linef('Account: %s', $Account->name());
            $this->linef('Cost basis: %s', $this->moneyFormat($Account->totalCost()));
            
            $table = $this->table(['Shares', 'Price'], $out['sells']);
            // $xPos += $table->width() + 1;
            $table->print();
            
            $this->linef('Proceeds: %s', $this->moneyFormat($sum));
            $this->linef('Profit: %s', $this->moneyFormat($sum - $Account->totalCost()));
            $this->line();

            $this->pause();
        });
    }

    public function addAccount()
    {
        $name = $this->prompt('Name of the account: ', null, true);
        $shares = $this->prompt('Number of shares: ', null, true);
        $cost = $this->prompt('Cost per share: $', null, true);

        $this->Accounts->set($name, new Account($name, $shares, $cost));
    }

    public function deleteAccount(Account $Account)
    {
        if ($this->getAccounts()->delete($Account)) {
            return true;
        }
        return false;
    }
    
    public function editAccount(Account $Account)
    {
        $this->deleteAccount($Account);

        $Account->setName($this->prompt('Name of the account: ', $Account->name(), true));
        $Account->setShares($this->prompt('Number of shares: ', $Account->shares(), true));
        $Account->setCost($this->prompt('Cost per share: $', $Account->cost(), true));

        $this->Accounts->set($Account->name(), $Account);
    }

    public function findAccount(string $name): ?Account
    {
        return $this->Accounts->get($name);
    }

    public function getAccounts(): PhpCli\Collection
    {
        if (isset($this->Accounts)) {
            return $this->Accounts;
        }
        return new PhpCli\Collection;
    }

    public function getMinPrice(): ?float
    {
        if (isset($this->minPrice)) {
            return $this->minPrice;
        }
        return null;
    }

    public function getMaxPrice(): ?float
    {
        if (isset($this->maxPrice)) {
            return $this->maxPrice;
        }
        return null;
    }


    public function moneyFormat(float $number)
    {
        return $this->formatter->formatCurrency($number, 'USD');
    }

    public function printAccounts()
    {
        $positions = $this->getAccounts()->map(function (Account $Account, $name) {
            return [
                $Account->name(),
                $Account->shares(),
                $this->moneyFormat($Account->totalCost()),
                $this->moneyFormat($Account->cost())
            ];
        })->toArray();
        
        $this->line('Account Positions');
        $this->table([
            'Name', 'Shares', '$Basis', '$/Share'
        ], $positions)->print();
    }

    public function setMinPrice(float $price)
    {
        $this->minPrice = $price;
        return $this;
    }

    public function setMaxPrice(float $price)
    {
        $this->maxPrice = $price;
        return $this;
    }




    protected function init(): void
    {
        $this->Accounts = new PhpCli\Collection();
        $this->formatter = new \NumberFormatter('en_US', \NumberFormatter::CURRENCY);
    }

    protected function defineOptions($options = []): array
    {
        // --path vendor/bin/phpunit
        // $options[] = new PhpCli\Option(
        //     $flagName = 'path',
        //     $requiresValue = true,
        //     $description = 'Path to PhpUnit binary, default is: ' . $defaultValue,
        //     $defaultValue
        // );

        return parent::defineOptions($options);
    }

    protected function defineArguments($arguments = []): array
    {
        // $arguments[] = new PhpCli\Argument('directory', $requiresValue = false, $defaultValue = 'tests');
        
        return parent::defineArguments($arguments);
    }

    public function run($command = null): int
    {
        // if ($this->path) {
        //     $this->defaultCommand->setBinary($this->path);
        //     $this->Parameters->drop('path');
        // }

        // return (int) $this->defaultCommand->run();
        
        
        $this->screen();

        return parent::run($command);
    }
}

$app = new SellLimits();
$app->setDefaultPrompt(' > ');

$app->defineMainMenu([
	'acc' => 'Accounts',
    'min' => 'Set miniumum price target',
    'max' => 'Set maxiumum price target',
    'sho' => 'Show a summary of positions',
    'run' => 'Show sell prices and quantities',
	'cmd' => 'Display this command menu'
], 'Enter Command: ');

$app->defineMenu('account', [
    'add' => 'Define an account with average cost',
    'mod' => 'Update an account',
    'del' => 'Delete an account',
    'bak' => 'Back to previous menu'
], 'Choose [bak]: ');

/**
 * Define application commands.
 */

$app->bind('cmd,commands', function ($app) {
	$app->menu(PhpCli\Application::MAIN_MENU_NAME, 'Main Menu:');
});

$app->bind('acc,account', function (PhpCli\Application $app) {
    $app->clear();
    
    while (true) {
        $Accounts = $app->getAccounts();
        $app->line('Accounts');
        $app->index($Accounts, [
            'name' => 'Name',
            'shares' => 'Shares',
            'cost' => 'Cost/Share'
        ], true)->print();

        // Display Accounts
        if (!$Accounts->empty()) {
            $accountOptions = array_values($Accounts->map(function ($Account) {
                return $Account->name();
            })->toArray());
        }

        // Menu prompt
        switch ($app->menuPrompt('account', null, 'Menu')) {
            case 'add':
                $app->addAccount();
                $app->clear();
                continue 2;
            break;
            case 'mod':
                if ($Accounts->empty()) {
                    $app->line('No accounts to modify.');
                    sleep(1);
                } else {
                    $selection = (int) $app->prompt('Modify which account: ');
                    $app->editAccount($Accounts->get($accountOptions[--$selection]));
                }
                $app->clear();
                continue 2;
            case 'del':
                if ($Accounts->empty()) {
                    $app->line('No accounts to delete.');
                    sleep(1);
                } else {
                    $selection = (int) $app->prompt('Delete which account: ');
                    if ($app->deleteAccount($Accounts->get($accountOptions[--$selection]))) {
                        $app->line('Account deleted.');
                        sleep(1);
                    }
                }
                $app->clear();
                continue 2;
            break;
            case 'bak':
            default:
                break 2;
        }
    }
});

$app->bind('min', function ($app) {
    $app->clear();
    $price = $app->prompt('Minimum share price: $', 0.00, true);
	$app->setMinPrice((float) $price);
});

$app->bind('max', function ($app) {
    $app->clear();
    $price = $app->prompt('Maximum share price: $', 0.00, true);
	$app->setMaxPrice((float) $price);
});

$app->bind('sho,info', function (PhpCli\Application $app) {
    $app->clear();
    if ($price = $app->getMinPrice()) {
        $app->linef('Minimum share price: %s', $app->moneyFormat($price));
    }
    if ($price = $app->getMaxPrice()) {
        $app->linef('Maximum share price: %s', $app->moneyFormat($price));
    }
    $app->printAccounts();
    $app->pause();
});

$app->bind('run', function (PhpCli\Application $app) {
    $app->clear();
    $app->calculate();
    // $app->pause();
});

try {
    $app->run();
} catch (\Exception $e) {
    $error = $e->getMessage();
    exit();
}