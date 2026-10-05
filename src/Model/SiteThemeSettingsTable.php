<?php
  namespace AgileThemeTools\Model;

  use Laminas\Db\TableGateway\TableGatewayInterface;
  use Laminas\Db\TableGateway\TableGateway;

  /**
   * Exploratory class. Not used.
   */

  class SiteThemeSettingsTable extends TableGateway {

    protected $tableGateway;

    public function __construct(TableGatewayInterface $tableGateway)
    {
      $this->tableGateway = $tableGateway;
      parent::__construct();
    }

    public function saveEntry(array $data): void
    {
      $this->tableGateway->insert($data);
    }
  }
