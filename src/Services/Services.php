<?php
/**
 * (c) 2013 Bossanova PHP Framework 5
 * https://bossanova.uk/php-framework
 *
 * @category PHP
 * @package  Bossanova
 * @author   Paul Hodel <paul.hodel@gmail.com>
 * @license  The MIT License (MIT)
 * @link     https://bossanova.uk/php-framework
 *
 * Services Library
 */
namespace bossanova\Services;

use bossanova\Model\Model;
use bossanova\Mail\Mail;
use bossanova\Mail\AdapterPhpmailer AS Adapter;

class Services
{
    public $mail = null;
    public $model = null;

    public function __construct(Model $model = null)
    {
        if (isset($model)) {
            $this->model = $model;
        }
    }

    /**
     * Basic select operation
     *
     * @param integer $id : record to be loaded
     * @return array $data : record data
     */
    public function select($id)
    {
        $data = $this->model->getById($id);

        if (! $data) {
            $data = [
                'error' => 1,
                'message' => '^^[Record not found]^^'
            ];
        }

        return $data;
    }

    /**
     * Basic insert operation
     *
     * @param array $row : columns to be saved
     *
     * @return array $data : return message
     */
    public function insert($row)
    {
        $id = $this->model->column($row)->insert();

        if (! $id) {
            $data = [
                'error' => 1,
                'message' => '^^[It was not possible to save your record]^^: '
                    . $this->model->getError()
            ];
        } else {
            $data = [
                'id' => $id,
                'message' => '^^[Successfully saved]^^',
            ];
        }

        return $data;
    }

    /**
     * Basic update operation
     *
     * @param integer $id : record to be changed
     * @param array $row : update columns
     *
     * @return array $data : return message
     */
    public function update($id, $row)
    {
        $data = $this->model->column($row)->update($id); // TODO: implementar seguranca

        if (! $data) {
            $data = [
                'error' => 1,
                'message' => '^^[It was not possible to save your record]^^: '
                    . $this->model->getError()
            ];
        } else {
            $data = [
                'message' => '^^[Successfully saved]^^',
            ];
        }

        return $data;
    }

    /**
     * Basic delete operation
     *
     * @param integer $id : record to be deleted
     *
     * @return array $data : return message
     */
    public function delete($id)
    {
        $data = $this->model->delete($id);

        if (! $data) {
            $data = [
                'error' => 1,
                'message' => '^^[It was not possible to delete your record]^^: '
                    . $this->model->getError()
            ];
        } else {
            $data = [
                'message' => '^^[Successfully saved]^^',
            ];
        }

        return $data;
    }

    /**
     * Grid
     *
     * @return array $data : grid data
     */
    public function grid(Grid $gridAdapter)
    {
        $data = $this->model->grid();

        return $gridAdapter->get($data);
    }

    /**
     * Default sendmail function, used by the modules to send used email
     *
     * @return void
     */
    public function sendmail($to, $subject, $html, $from, $files = null)
    {
        if (! $this->mail) {
            $this->mail = new Mail(new Adapter());
        }

        ob_start();
        $instance = $this->mail->sendmail($to, $subject, $html, $from, $files);
        ob_get_clean();

        return $instance;
    }
}
