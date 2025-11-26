<?php

namespace Tabula17\Satelles\Omnia\Roga\Database;

use Tabula17\Satelles\Omnia\Roga\Exception\ExceptionDefinitions;
use Tabula17\Satelles\Omnia\Roga\Exception\InvalidArgumentException;
use Tabula17\Satelles\Utilis\Config\ConnectionConfig;

/**
 * Represents the configuration settings required for a database connection.
 *
 * This class encapsulates the details needed to establish a connection,
 * such as the database driver, host, port, credentials, and additional options.
 * It also provides functionality to validate connectivity and access configuration details.
 */
class DbConfig extends ConnectionConfig
{
    /**
     * The database driver used to establish the connection.
     * This can be either a string representing the driver name or an instance of DriversEnum.
     * @var DriversEnum $driver
     */
    protected(set) DriversEnum $driver {
        set(DriversEnum|string $driver) {
            if (is_string($driver)) {
                $driver = DriversEnum::fromName($driver);
            }
            if ($driver instanceof DriversEnum) {
                $this->driver = $driver;
            } else {
                throw new InvalidArgumentException(sprintf(ExceptionDefinitions::DATABASE_DRIVER_NOT_FOUND->value, $driver));
            }
        }
    }

    /**
     * Checks if a connection to the specified host and port can be established.
     *
     * @return bool Returns true if the connection is successful and the driver is available, otherwise false.
     */
    public function canConnect(): bool
    {
        if (!DriversEnum::isAvailable($this->driver)) {
            $driver = $this->driver->value ?? 'unknown';
            $this->lastConnectionError = "El driver para la conexión del tipo '$driver' no es válido o no está disponible.";
            return false;
        }
        return parent::canConnect();
    }

}