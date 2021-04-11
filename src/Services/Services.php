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
use bossanova\Config\Config;

class Services
{
    public $mail = null;
    public $model = null;
    public $user_id = null;

    public function __construct(Model $model = null)
    {
        if (isset($model)) {
            $this->model = $model;
        }

        return $this;
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
        } else if ($this->user_id && $this->user_id != $data['user_id']) {
            $data = [
                'error' => 1,
                'message' => '^^[Permission denied]^^'
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
                'success' => 1,
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
        if ($this->user_id) {
            $data = $this->model->getById($id);

            if ($this->user_id != $data['user_id']) {
                return [
                    'error' => 1,
                    'message' => '^^[Permission denied]^^'
                ];
            }
        }

        $data = $this->model->column($row)->update($id);

        if (! $data) {
            $data = [
                'error' => 1,
                'message' => '^^[It was not possible to save your record]^^: '
                    . $this->model->getError()
            ];
        } else {
            $data = [
                'success' => 1,
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
        if ($this->user_id) {
            $data = $this->model->getById($id);

            if ($this->user_id != $data['user_id']) {
                return [
                    'error' => 1,
                    'message' => '^^[Permission denied]^^'
                ];
            }
        }

        $data = $this->model->delete($id);

        if (! $data) {
            $data = [
                'error' => 1,
                'message' => '^^[It was not possible to delete your record]^^: '
                    . $this->model->getError()
            ];
        } else {
            $data = [
                'success' => 1,
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
            // Get preferable mail adapter
            $adapter = Config::get('mail');
            // Create instance
            $this->mail = new Mail($adapter);
        }

        ob_start();
        $instance = $this->mail->sendmail($to, $subject, $html, $from, $files);
        ob_get_clean();

        return $instance;
    }
}
