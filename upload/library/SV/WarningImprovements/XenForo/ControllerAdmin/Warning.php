<?php

class SV_WarningImprovements_XenForo_ControllerAdmin_Warning extends XFCP_SV_WarningImprovements_XenForo_ControllerAdmin_Warning
{
    public function actionIndex()
    {
        $view = parent::actionIndex();
        $view->params['warningEscalatingDefaults'] = $this->_getWarningModel()->getWarningDefaultExtentions();
        return $view;
    }

    public function actionLoadTree()
    {
        $this->_assertPostOnly();

        $this->_routeMatch->setResponseType('json');

        $viewParams = array(
            'tree' => $this->_getWarningModel()->getWarningItemTree()
        );

        return $this->responseView(
            'XenForo_ViewAdmin_Warning_LoadTree',
            '',
            $viewParams
        );
    }

    public function actionSyncTree()
    {
        $this->_assertPostOnly();

        $tree = $this->_input->filterSingle('tree', XenForo_Input::JSON_ARRAY);

        $warningItems = $this->_getWarningModel()->processWarningItemTree($tree);

        foreach ($warningItems as $warningItem)
        {
            if ($warningItem['type'] == 'category')
            {
                $dw = XenForo_DataWriter::create(
                    'SV_WarningImprovements_DataWriter_WarningCategory'
                );
                $dw->setExistingData($warningItem['id']);
                $dw->bulkSet(array(
                    'parent_warning_category_id' => $warningItem['parent'],
                    'display_order'              => $warningItem['display_order']
                ));
                $dw->save();
            }
            elseif ($warningItem['type'] == 'definition')
            {
                $dw = XenForo_DataWriter::create(
                    'XenForo_DataWriter_WarningDefinition'
                );

                if ($warningItem['id'] === 0)
                {
                    $dw->setOption(
                        SV_WarningImprovements_XenForo_DataWriter_WarningDefinition::IS_CUSTOM,
                        1
                    );
                }

                $dw->setExistingData($warningItem['id']);
                $dw->bulkSet(array(
                    'sv_warning_category_id' => $warningItem['parent'],
                    'sv_display_order'       => $warningItem['display_order']
                ));
                $dw->save();
            }
        }

        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
            XenForo_Link::buildAdminLink('warnings')
        );
    }

    public function actionRenameTreeItem()
    {
        $this->_assertPostOnly();

        $node = $this->_input->filterSingle('node', XenForo_Input::JSON_ARRAY);

        $warningItem = $this->_getWarningModel()
            ->processWarningItemTreeItem($node);

        if ($warningItem['type'] == 'category')
        {
            $dw = XenForo_DataWriter::create(
                'SV_WarningImprovements_DataWriter_WarningCategory'
            );
            $dw->setExistingData($warningItem['id']);
            $dw->setExtraData(
                SV_WarningImprovements_DataWriter_WarningCategory::DATA_TITLE,
                $warningItem['title']
            );
            $dw->save();

            $hash = $this->getLastHash(
                'category-'.$dw->get('warning_category_id')
            );
        }
        elseif ($warningItem['type'] == 'definition')
        {
            $dw = XenForo_DataWriter::create(
                'XenForo_DataWriter_WarningDefinition'
            );
            $dw->setExistingData($warningItem['id']);
            $dw->setExtraData(
                XenForo_DataWriter_WarningDefinition::DATA_TITLE,
                $warningItem['title']
            );
            $dw->save();

            $hash = $this->getLastHash(
                'warning-'.$dw->get('warning_definition_id')
            );
        }

        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
            XenForo_Link::buildAdminLink('warnings').$hash
        );
    }

    var $_set_custom_warning = false;

    public function actionEdit()
    {
        $warningDefinitionId = $this->_input->filterSingle('warning_definition_id', XenForo_Input::UINT);
        $this->_set_custom_warning = empty($warningDefinitionId);
        $view = parent::actionEdit();
        if ($this->_set_custom_warning)
        {
            $masterValues = $this->_getWarningModel()->getWarningDefinitionMasterPhraseValues($warningDefinitionId);
            $view->params['masterTitle'] = $masterValues['title'];
            $view->params['masterConversationTitle'] = $masterValues['conversationTitle'];
            $view->params['masterConversationText'] = $masterValues['conversationText'];
            $this->_set_custom_warning = false;
        }
        return $view;
    }

    protected function _getWarningAddEditResponse(array $warning)
    {
        $warning['is_custom'] = $this->_set_custom_warning;

        $response = parent::_getWarningAddEditResponse($warning);
        $viewParams = &$response->params;

        $viewParams['warningCategories'] = $this->_getWarningModel()
            ->getWarningCategoryOptions();

        return $response;
    }

    public function actionSave()
    {
        $warningDefinitionId = $this->_input->filterSingle(
            'warning_definition_id',
            XenForo_Input::UINT
        );
        $isCustom = $this->_input->filterSingle(
            'is_custom',
            XenForo_Input::UINT
        );

        if ($warningDefinitionId == 0 && $isCustom)
        {
            $dwInput = $this->_input->filter(array(
                'points_default'       => XenForo_Input::UINT,
                'expiry_type'          => XenForo_Input::STRING,
                'expiry_default'       => XenForo_Input::UINT,
                'extra_user_group_ids' => array(
                    XenForo_Input::UINT,
                    'array' => true
                ),
                'is_editable'          => XenForo_Input::UINT
            ));
            $phrases = $this->_input->filter(array(
                'title'             => XenForo_Input::STRING,
                'conversationTitle' => XenForo_Input::STRING,
                'conversationText'  => XenForo_Input::STRING
            ));

            $expiryType = $this->_input->filterSingle(
                'expiry_type_base',
                XenForo_Input::STRING
            );

            if ($expiryType == 'never')
            {
                $dwInput['expiry_type'] = 'never';
            }

            $dw = XenForo_DataWriter::create(
                'XenForo_DataWriter_WarningDefinition'
            );
            $dw->setOption(
                SV_WarningImprovements_XenForo_DataWriter_WarningDefinition::IS_CUSTOM,
                1
            );
            $dw->setExistingData($warningDefinitionId);
            $dw->bulkSet($dwInput);
            $dw->setExtraData(
                XenForo_DataWriter_WarningDefinition::DATA_TITLE,
                $phrases['title']
            );
            $dw->setExtraData(
                XenForo_DataWriter_WarningDefinition::DATA_CONVERSATION_TITLE,
                $phrases['conversationTitle']
            );
            $dw->setExtraData(
                XenForo_DataWriter_WarningDefinition::DATA_CONVERSATION_TEXT,
                $phrases['conversationText']
            );
            $dw->save();

            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::SUCCESS,
                XenForo_Link::buildAdminLink('warnings') . $this->getLastHash(
                    'warning-'.$dw->get('warning_definition_id')
                )
            );
        }

        SV_WarningImprovements_Globals::$warningDefinitionInput = $this->_input->filter(
            array(
                'sv_warning_category_id' => XenForo_Input::UINT,
                'sv_display_order'       => XenForo_Input::UINT
            )
        );

        $response = parent::actionSave();

        SV_WarningImprovements_Globals::$warningDefinitionInput = null;

        return $response;
    }

    protected function _getActionAddEditResponse(array $action)
    {
        $response = parent::_getActionAddEditResponse($action);
        if ($response instanceof XenForo_ControllerResponse_View)
        {
            $nodeList = array();
            $nodeModel = XenForo_Model::create('XenForo_Model_Node');

            $nodeList = $nodeModel->getNodeOptionsArray(
                $nodeModel->getAllNodes(),
                empty($action['sv_post_node_id']) ? 0 : $action['sv_post_node_id'],
                sprintf('(%s)', new XenForo_Phrase('unspecified'))
            );

            foreach ($nodeList AS &$option)
            {
                if (!empty($option['node_type_id']) && $option['node_type_id'] != 'Forum')
                {
                    $option['disabled'] = 'disabled';
                }

                unset($nodeList['node_type_id']);
            }

            $response->params['nodeList'] = $nodeList;
        }

        return $response;
    }

    protected function _getDefaultAddEditResponse(array $warningDefault)
    {
        $viewParams = array(
            'default' => $warningDefault,
        );
        return $this->responseView('XenForo_ViewAdmin_Warning_DefaultEdit', 'sv_warningimprovements_warning_default_edit', $viewParams);
    }

    public function actionDefaultAdd()
    {
        return $this->_getDefaultAddEditResponse(array(
            'threshold_points' => $this->_getWarningModel()->getLastWarningDefault() + 100,
            'expiry_extension' => 1,
            'expiry_type' => 'days',
            'active' => 1,
        ));
    }

    public function actionDefaultEdit()
    {
        $warningDefaultId = $this->_input->filterSingle('warning_default_id', XenForo_Input::UINT);
        $action = $this->_getWarningDefaultOrError($warningDefaultId);

        return $this->_getDefaultAddEditResponse($action);
    }

    public function actionDefaultSave()
    {
        $warningDefaultId = $this->_input->filterSingle('warning_default_id', XenForo_Input::UINT);

        $dwInput = $this->_input->filter(array(
            'threshold_points' => XenForo_Input::UINT,
            'expiry_extension' => XenForo_Input::UINT,
            'expiry_type' => XenForo_Input::STRING,
            'active' => XenForo_Input::BOOLEAN,
        ));

        $dw = XenForo_DataWriter::create('SV_WarningImprovements_DataWriter_WarningDefault');
        if ($warningDefaultId)
        {
            $dw->setExistingData($warningDefaultId);
        }
        $dw->bulkSet($dwInput);
        $dw->save();

        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildAdminLink('warnings') . '#_warning_default-' . $dw->get('warning_default_id')
        );
    }

    public function actionDefaultDelete()
    {
        if ($this->isConfirmedPost())
        {
            return $this->_deleteData(
                'SV_WarningImprovements_DataWriter_WarningDefault', 'warning_default_id',
                XenForo_Link::buildAdminLink('warnings')
            );
        }
        else
        {
            $warningDefaultId = $this->_input->filterSingle('warning_default_id', XenForo_Input::UINT);
            $default = $this->_getWarningDefaultOrError($warningDefaultId);

            $viewParams = array(
                'default' => $default
            );

            return $this->responseView('XenForo_ViewAdmin_Warning_DefaultDelete', 'sv_warningimprovements_warning_default_delete', $viewParams);
        }
    }

    protected function _getWarningDefaultOrError($id)
    {
        $result = $this->getRecordOrError(
            $id, $this->_getWarningModel(), 'getWarningDefaultById',
            'sv_requested_warning_default_not_found'
        );

        return $result;
    }

    public function actionActionSave()
    {
        SV_WarningImprovements_Globals::$warningActionInput = $this->_input->filter(array(
            'sv_post_node_id' => XenForo_Input::UINT,
            'sv_post_thread_id' => XenForo_Input::UINT,
            'sv_post_as_user_id' => XenForo_Input::UINT,
        ));
        return parent::actionActionSave();
    }

    protected function _getCategoryAddEditResponse(array $warningCategory)
    {
        $warningCategories = $this->_getWarningModel()
            ->getWarningCategoryOptions(true);

        if (isset($warningCategory['warning_category_id']) &&
            isset($warningCategories[$warningCategory['warning_category_id']])
        ) {
            unset($warningCategories[$warningCategory['warning_category_id']]);
        }

        $userGroups = $this->getModelFromCache('XenForo_Model_UserGroup')
            ->getUserGroupOptions($warningCategory['allowed_user_group_ids']);

        $viewParams = array(
            'warningCategory'   => $warningCategory,
            'warningCategories' => $warningCategories,
            'userGroups'        => $userGroups
        );

        return $this->responseView(
            'XenForo_ViewAdmin_Warning_CategoryEdit',
            'sv_warning_category_edit',
            $viewParams
        );
    }

    public function actionCategoryAdd()
    {
        return $this->_getCategoryAddEditResponse(array(
            'display_order'          => 0,
            'allowed_user_group_ids' => '2'
        ));
    }

    public function actionCategoryEdit()
    {
        $warningCategoryId = $this->_input->filterSingle(
            'warning_category_id',
            XenForo_Input::UINT
        );
        $warningCategory = $this->_getWarningCategoryOrError($warningCategoryId);

        return $this->_getCategoryAddEditResponse($warningCategory);
    }

    public function actionCategorySave()
    {
        $this->_assertPostOnly();

        $warningCategoryId = $this->_input->filterSingle(
            'warning_category_id',
            XenForo_Input::UINT
        );

        $dwInput = $this->_input->filter(array(
            'warning_category_id'        => XenForo_Input::STRING,
            'parent_warning_category_id' => XenForo_Input::UINT,
            'display_order'              => XenForo_Input::UINT,
            'allowed_user_group_ids'     => array(
                XenForo_Input::UINT,
                'array' => true
            )
        ));

        $titlePhrase = $this->_input->filterSingle(
            'title',
            XenForo_Input::STRING
        );

        $dw = XenForo_DataWriter::create(
            'SV_WarningImprovements_DataWriter_WarningCategory'
        );
        if ($warningCategoryId)
        {
            $dw->setExistingData($warningCategoryId);
        }
        $dw->bulkSet($dwInput);
        $dw->setExtraData(
            SV_WarningImprovements_DataWriter_WarningCategory::DATA_TITLE,
            $titlePhrase
        );
        $dw->save();

        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildAdminLink('warnings') . $this->getLastHash(
                'category-'.$dw->get('warning_category_id')
            )
        );
    }

    public function actionCategoryDelete()
    {
        if ($this->isConfirmedPost())
        {
            return $this->_deleteData(
                'SV_WarningImprovements_DataWriter_WarningCategory',
                'warning_category_id',
                XenForo_Link::buildAdminLink('warnings')
            );
        }
        else
        {
            $warningCategoryId = $this->_input->filterSingle(
                'warning_category_id',
                XenForo_Input::STRING
            );
            $warningCategory = $this->_getWarningCategoryOrError(
                $warningCategoryId
            );

            $viewParams = array(
                'warningCategory' => $warningCategory
            );

            return $this->responseView(
                'XenForo_ViewAdmin_Warning_CategoryDelete',
                'sv_warning_category_delete',
                $viewParams
            );
        }
    }

    protected function _getWarningCategoryOrError($warningCategoryId)
    {
        $warningModel = $this->_getWarningModel();

        $warningCategory = $warningModel->getWarningCategoryById($warningCategoryId);
        if (!$warningCategory)
        {
            throw $this->responseException($this->responseError(
                new XenForo_Phrase('sv_requested_warning_category_not_found'),
                404
            ));
        }

        $warningCategory = $warningModel->prepareWarningCategory($warningCategory);

        return $warningCategory;
    }
}
