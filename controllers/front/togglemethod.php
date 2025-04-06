<?php

class Vc_prontopagaToggleMethodModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        if (!$this->ajax || !$this->isJsonRequest()) {
            $this->sendJsonError('Invalid request type.');
        }

        /*if (!$this->context->employee || !$this->context->employee->isLoggedBack()) {
            $this->sendJsonError('Unauthorized access.', 403);
        }*/

        $idMethod = (int) Tools::getValue('id_method');
        $newStatus = (int) Tools::getValue('new_status');

        if (!$idMethod || !in_array($newStatus, [0, 1])) {
            $this->sendJsonError('Invalid parameters.');
        }

        $exists = Db::getInstance()->getValue('
            SELECT COUNT(*)
            FROM `' . _DB_PREFIX_ . 'vc_prontopaga_methods`
            WHERE id = ' . (int) $idMethod
        );

        if (!$exists) {
            $this->sendJsonError('Payment method not found.');
        }

        $updated = Db::getInstance()->update(
            'vc_prontopaga_methods',
            ['active' => $newStatus],
            'id = ' . (int) $idMethod
        );

        if (!$updated) {
            $this->sendJsonError('Failed to update the method.');
        }

        $this->ajaxDie(json_encode([
            'success' => true,
            'message' => 'Payment method updated successfully.',
            'new_status' => $newStatus,
        ]));
    }

    private function sendJsonError($message, $code = 400)
    {
        http_response_code($code);
        $this->ajaxDie(json_encode([
            'success' => false,
            'message' => $message,
        ]));
    }

    private function isJsonRequest()
    {
        return (Tools::getValue('ajax') || Tools::getIsset('ajax'));
    }
}