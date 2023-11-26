<?php

/**
 * This file is part of the Zephir.
 *
 * (c) Phalcon Team <team@zephir-lang.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Zephir\Backend;

use Zephir\Code\Printer;
use Zephir\FileSystem\HardDisk;

class FcallManager implements FcallManagerInterface
{
    protected array $requiredMacros = [];

    /**
     * @param bool $static
     * @param int  $doReturn   tri-state: 0 -> no return value, 1 -> do return, 2 -> do return to given variable
     * @param int  $paramCount
     *
     * @return string
     */
    public function getMacro(bool $static, int $doReturn, int $paramCount): string
    {
        $scope = $static ? 'STATIC' : '';
        $mode = 'CALL_INTERNAL_METHOD_NORETURN_P';

        if ($doReturn) {
            $mode = 'RETURN_CALL_INTERNAL_METHOD_P';
            if (2 === $doReturn) {
                $mode = 'CALL_INTERNAL_METHOD_P';
            }
        }

        $macroName = 'ZEPHIR_'.($scope ? $scope.'_' : '').$mode.$paramCount;

        if (!isset($this->requiredMacros[$macroName])) {
            $this->requiredMacros[$macroName] = [$scope, $mode, $paramCount];
        }

        return $macroName;
    }

    public function genFcallCode(): void
    {
        $codePrinter = new Printer();

        $header = <<<HEAD
/*
 * This file was generated automatically by Zephir.
 * DO NOT EDIT THIS FILE BY HAND -- YOUR CHANGES WILL BE OVERWRITTEN
 */

#ifndef ZEPHIR_KERNEL_FCALL_INTERNAL_H
#define ZEPHIR_KERNEL_FCALL_INTERNAL_H

HEAD;
        $codePrinter->output($header);

        ksort($this->requiredMacros);
        foreach ($this->requiredMacros as $name => $info) {
            [$scope, $mode, $paramCount] = $info;
            $paramsStr = '';
            $retParam = '';
            $retValueUsed = '0';
            $params = [];
            $zvals = [];
            $initStatements = [];
            $postStatements = [];

            for ($i = 0; $i < $paramCount; ++$i) {
                $params[] = 'p'.$i;
            }
            if ($paramCount) {
                $paramsStr = ', '.implode(', ', $params);
            }

            if ('CALL_INTERNAL_METHOD_P' == $mode) {
                $retValueUsed = '1';
                $retParam = 'return_value_ptr';
                $initStatements[] = 'ZEPHIR_INIT_NVAR((return_value_ptr)); \\';
            }
            $objParam = $scope ? 'scope_ce, ' : 'object, ';
            $macroName = $name.'('.($retParam ? $retParam.', ' : '').$objParam.'method'.$paramsStr.')';
            $codePrinter->output('#define '.$macroName.' \\');
            if (!$retParam) {
                $retParam = 'return_value';
            }
            $codePrinter->increaseLevel();
            $codePrinter->output('do { \\');
            $codePrinter->increaseLevel();

            if ('CALL_INTERNAL_METHOD_NORETURN_P' == $mode) {
                $codePrinter->output('zval rv; \\');
                $codePrinter->output('zval *rvp = &rv; \\');
                $codePrinter->output('ZVAL_UNDEF(&rv); \\');
                $retParam = 'rvp';
            }

            $codePrinter->output('ZEPHIR_BACKUP_SCOPE(); \\');
            if (!$scope) {
                $codePrinter->output('ZEPHIR_SET_THIS(object); \\');
                $codePrinter->output('ZEPHIR_SET_SCOPE((Z_OBJ_P(object) ? Z_OBJCE_P(object) : NULL), (Z_OBJ_P(object) ? Z_OBJCE_P(object) : NULL)); \\');
            } else {
                $codePrinter->output('ZEPHIR_SET_THIS_EXPLICIT_NULL(); \\');
                $codePrinter->output('ZEPHIR_SET_SCOPE(scope_ce, scope_ce); \\');
            }

            /* Create new zval's for parameters */
            for ($i = 0; $i < $paramCount; ++$i) {
                $zv = '_'.$params[$i];
                $zvals[] = $zv;
                $initStatements[] = 'ZVAL_COPY(&'.$zv.', '.$params[$i].'); \\';
                $postStatements[] = 'Z_TRY_DELREF_P('.$params[$i].'); \\';
            }
            if ($i) {
                $codePrinter->output('zval '.implode(', ', $zvals).'; \\');
            }
            foreach ($initStatements as $statement) {
                $codePrinter->output($statement);
            }

            $codePrinter->output(
                sprintf(
                    'method(0, execute_data, %s, %s%s%s); \\',
                    $retParam,
                    $scope ? 'NULL, ' : $objParam,
                    $retValueUsed,
                    $i ? ', &'.implode(', &', $zvals) : ''
                )
            );

            if ('CALL_INTERNAL_METHOD_NORETURN_P' == $mode) {
                $postStatements[] = 'zval_ptr_dtor(rvp); \\';
            }

            foreach ($postStatements as $statement) {
                $codePrinter->output($statement);
            }

            $codePrinter->output('ZEPHIR_LAST_CALL_STATUS = EG(exception) ? FAILURE : SUCCESS; \\');

            $codePrinter->output('ZEPHIR_RESTORE_SCOPE(); \\');
            $codePrinter->decreaseLevel();
            $codePrinter->output('} while (0)');
            $codePrinter->decreaseLevel();
            $codePrinter->output('');
        }

        $codePrinter->output('#endif');
        HardDisk::persistByHash($codePrinter->getOutput(), 'ext/kernel/fcall_internal.h');
    }
}
