<?php

declare(strict_types=1);

class AutologinServer implements \JsonSerializable
{
    protected string $server;
    protected string $username;
    protected string $password;

    protected string $db;
    protected string $label;
    protected string $driver = 'server';

    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string $host,
        string $username,
        string $password,
        ?int $port = null,
        string $label = '',
        string $driver = 'server',
        string $db = ''
    ) {

        $server = explode(':', $host, 2);

        if (count($server) === 2) {
            $host = trim($server[0]);
            $port = trim($server[1]);
        }

        if ($port === null) {
            throw new \InvalidArgumentException(
                'Port not provided in host string. Please provide a port via host:port or utilize the port parameter.'
            );
        }

        $this->server = $host . ':' . $port;

        $this->username = $username;
        $this->password = $password;
        $this->label = $label;
        $this->driver = $driver;
        $this->db = $db;

        if (empty($label)) {
            $this->label = $this->server;
            if (!empty($db)) {
                $this->label .= ' [' . $db . ']';
            }
        }
    }

    public function getOptionTag($selected = false)
    {
        $html = '<option value="' . $this->server . '" driver="' . $this->driver . '"';
        $html .= ($selected ? ' selected>' : '>');
        $html .= $this->label . '</option>';

        return $html;
    }


    public function getServer(): string
    {
        return $this->server;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getDatabase(): string
    {
        return $this->db;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function jsonSerialize(): mixed
    {
        return [
            "server" => $this->server,
            "username" => $this->username,
            "password" => $this->password,
            "label" => $this->label,
            "driver" => $this->driver,
            "db" => $this->db
        ];
    }
}


class JsonDecodeServerStatus
{
    protected string $error = '';

    /**
     * @var ?AutologinServer[]
     */
    protected ?array $servers;

    public function __construct($error = '', $servers = [])
    {
        $this->error = $error;
        $this->servers = $servers;
    }

    public function getError(): string
    {
        return $this->error;
    }

    /**
     * @return null|?AutologinServer[]
     */
    public function getServers(): ?array
    {
        return $this->servers;
    }
}


class AutologinConfigLoader
{
    /**
     * @var AutologinServer[]
     */
    private array $servers = [];
    // Array of error strings encountered during loading
    private array $errors = [];

    function __construct() {}

    public function load(): self
    {
        // 1. Load from optional JSON file
        if (file_exists('connections.json')) {
            $fileContent = file_get_contents('connections.json');
            if ($fileContent !== false) {
                try {
                    $status = $this->safeJsonDecode($fileContent, 'connections.json');

                    if (!empty($status->getServers())) {
                        $this->servers = array_merge($this->servers, $status->getServers());
                    } else {
                        $this->errors[] = $status->getError();
                    }
                } catch (\Exception $exception) {
                    $this->errors[] = $exception->getMessage();
                }
            }
        }

        // 2. Load from optional ENV variable
        if ($envJson = getenv('ADMINER_SERVERS_JSON')) {
            $status = $this->safeJsonDecode($envJson, 'ENV ADMINER_SERVERS_JSON');
            if (!empty($status->getServers())) {
                $this->servers = array_merge($this->servers, $status->getServers());
            } else {
                $this->errors[] = $status->getError();
            }
        }

        return $this;
    }

    /**
     * @param string $json
     * @param string $sourceName
     * @return JsonDecodeServerStatus
     * @throws \InvalidArgumentException
     */
    private function safeJsonDecode($json, $sourceName)
    {
        $data = json_decode($json, true, JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);

        // any json error
        if (json_last_error() !== JSON_ERROR_NONE || null === $data) {
            return new JsonDecodeServerStatus("$sourceName: JSON Error - " . json_last_error_msg());
        }

        if (!is_array($data) || !array_key_exists(0, $data)) {
            return new JsonDecodeServerStatus("$sourceName: JSON Error - Invalid Format. Content must be an array of objects.");
        }

        $servers = [];

        // map JSON data
        foreach ($data as $index => $server) {
            foreach (['server', 'username', 'password'] as $key) {
                if (!isset($server[$key])) {
                    return new JsonDecodeServerStatus("$sourceName: JSON Error - key '$key' is missing in server index '$index'.");
                }
            }

            $servers[] = new AutologinServer(
                $server['server'],
                $server['username'],
                $server['password'],
                (int) ($server['port'] ?? null),
                $server['label'] ?? '',
                $server['driver'] ?? 'server',
                $server['db'] ?? '',
            );
        }

        return new JsonDecodeServerStatus(servers: $servers);
    }

    public function getServers(): array
    {
        return $this->servers;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}

/**
 * main class
 */
class Autologin extends Adminer\Plugin
{

    /**
     * @var AutologinServer[]
     */
    private array $servers = [];
    private array $configErrors = [];

    protected $translations = [
        'en' => [
            '' => 'Adds a selection field for custom defined connections',
            'warning' => 'Warning: Don\'t use the Autologin plugin in production environments!',
            'select_connection' => 'Select Connection'
        ],
        'cs' => ['' => 'Přidá výběrové pole pro vlastní definovaná připojení'],
        'de' => [
            '' => 'Fügt ein Auswahlfeld für benutzerdefinierte Verbindungen hinzu',
            'warning' => 'Warnung: Nutze das Autologin plugin nicht in production Umgebungen!',
            'select_connection' => 'Verbindung auswählen'
        ],
        'pl' => ['' => 'Dodaje pole wyboru dla zdefiniowanych połączeń'],
        'ro' => ['' => 'Adaugă un câmp de selecție pentru conexiuni definite personalizat'],
        'ja' => ['' => 'カスタム定義された接続の選択フィールドを追加します'],
    ];

    /**
     * @param AutologinServer|AutologinServer[] $servers
     */
    public function __construct(AutologinServer|array $servers = [])
    {
        $configLoader = new AutologinConfigLoader();
        $configLoader->load();

        $this->servers = array_merge(
            $servers instanceof AutologinServer ? [$servers] : $servers,
            $configLoader->getServers()
        );
        $this->configErrors = $configLoader->getErrors();
    }

    public function loginForm(): void
    {
        // 1. DISPLAY CONFIGURATION ERRORS (Blocker)
        if (!empty($this->configErrors)) {
            echo '<div style="background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 15px; margin-bottom: 20px; border-radius: 4px;">';
            echo '<strong>Autologin Configuration Error:</strong><br>';
            echo '<ul style="margin: 5px 0 0 20px; padding: 0;">';
            foreach ($this->configErrors as $error) {
                echo '<li>' . htmlspecialchars($error) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
            // Return to allow standard login form to render below the error
            return;
        }

        // 2. DISPLAY SECURITY WARNING (If no errors)
?>
        <div style="background: #fff3cd; color: #856404; padding: 1rem; margin-block-start: 1rem; border-radius: 4px; font-weight: bold; display: inline-block"><?php echo $this->lang('warning') ?></div>
        <?php

        // 3. RENDER DROPDOWN LOGIC
        if (empty($this->servers)) {
            return;
        }

        $serversJson = json_encode($this->servers);
        ?>
        <script type="text/javascript" <?php echo Adminer\nonce(); ?>>
            (function() {
                var servers = <?php echo $serversJson; ?>;

                function updateLoginForm() {
                    var select = document.getElementById('server-select');
                    var selectedIndex = select.value;

                    if (selectedIndex === "") return;

                    var config = servers[selectedIndex];

                    function setFormField(name, value) {
                        var el = document.querySelector('[name="auth[' + name + ']"]');
                        if (el) el.value = value || "";
                    }

                    setFormField('driver', config.driver);
                    setFormField('server', config.server);
                    setFormField('username', config.username);
                    setFormField('password', config.password);
                    setFormField('db', config.db);

                    var form = document.querySelector('form');
                    if (form) form.submit();
                }

                document.addEventListener("DOMContentLoaded", function() {
                    // Find the table inside the login form (Adminer structure: form -> table)
                    var table = document.querySelector('#content form table tbody');
                    if (!table) return;

                    // Create a new table row
                    var tr = document.createElement('tr');

                    // Create the label cell (matches Adminer style <th>)
                    var th = document.createElement('th');
                    th.innerText = "Autologin"; // Text label

                    // Create the input cell (matches Adminer style <td>)
                    var td = document.createElement('td');

                    var select = document.createElement('select');
                    select.id = 'server-select';
                    select.innerHTML = '<option value="" disabled selected>-- <?php echo $this->lang('select_connection') ?> --</option>';

                    servers.forEach(function(conn, index) {
                        var option = document.createElement('option');
                        option.value = index;
                        option.innerText = conn.label ? conn.label : (conn.username + '@' + conn.server);
                        select.appendChild(option);
                    });

                    select.onchange = updateLoginForm;

                    // Assemble the row
                    td.appendChild(select);
                    tr.appendChild(th);
                    tr.appendChild(td);

                    const separatorRow = document.createElement('tr');
                    const separatorCell = document.createElement('td');
                    separatorCell.colSpan = 2;
                    separatorRow.appendChild(separatorCell);
                    separatorCell.style = 'background: var(--dim, #eee);';

                    table.appendChild(separatorRow);
                    table.appendChild(tr);
                });
            })();
        </script>
<?php
    }
}
