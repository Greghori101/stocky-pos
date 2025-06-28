"use strict";
(self["webpackChunk"] = self["webpackChunk"] || []).push([["edit_task"],{

/***/ "./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/lib/index.js??vue-loader-options!./resources/src/views/app/pages/tasks/Edit_task.vue?vue&type=script&lang=js":
/*!***************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/lib/index.js??vue-loader-options!./resources/src/views/app/pages/tasks/Edit_task.vue?vue&type=script&lang=js ***!
  \***************************************************************************************************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var nprogress__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! nprogress */ "./node_modules/nprogress/nprogress.js");
/* harmony import */ var nprogress__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(nprogress__WEBPACK_IMPORTED_MODULE_0__);

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ({
  metaInfo: {
    title: "Edit Task"
  },
  data: function data() {
    return {
      isLoading: true,
      SubmitProcessing: false,
      projects: [],
      companies: [],
      employees: [],
      assigned_employees: [],
      task: {
        title: "",
        description: "",
        project_id: "",
        company_id: "",
        assigned_to: [],
        start_date: "",
        end_date: "",
        status: ""
      }
    };
  },
  methods: {
    //------------- Submit Validation Create Task
    Submit_Task: function Submit_Task() {
      var _this = this;
      this.$refs.ref_edit_task.validate().then(function (success) {
        if (!success) {
          _this.makeToast("danger", _this.$t("Please_fill_the_form_correctly"), _this.$t("Failed"));
        } else {
          _this.Update_Task();
        }
      });
    },
    //------ Toast
    makeToast: function makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },
    //------ Validation State
    getValidationState: function getValidationState(_ref) {
      var dirty = _ref.dirty,
        validated = _ref.validated,
        _ref$valid = _ref.valid,
        valid = _ref$valid === void 0 ? null : _ref$valid;
      return dirty || validated ? valid : null;
    },
    //--------------------------------- Update_Task -------------------------\\
    Update_Task: function Update_Task() {
      var _this2 = this;
      this.SubmitProcessing = true;
      var self = this;
      // Start the progress bar.
      nprogress__WEBPACK_IMPORTED_MODULE_0___default().start();
      nprogress__WEBPACK_IMPORTED_MODULE_0___default().set(0.1);
      var id = this.$route.params.id;
      axios.put("tasks/".concat(id), {
        title: self.task.title,
        description: self.task.description,
        project_id: self.task.project_id,
        company_id: self.task.company_id,
        assigned_to: self.assigned_employees,
        start_date: self.task.start_date,
        end_date: self.task.end_date,
        status: self.task.status
      }).then(function (response) {
        // Complete the animation of theprogress bar.
        nprogress__WEBPACK_IMPORTED_MODULE_0___default().done();
        _this2.makeToast("success", _this2.$t("Successfully_Updated"), _this2.$t("Success"));
        _this2.SubmitProcessing = false;
        _this2.$router.push({
          name: "index_task"
        });
      })["catch"](function (error) {
        // Complete the animation of theprogress bar.
        nprogress__WEBPACK_IMPORTED_MODULE_0___default().done();
        _this2.makeToast("danger", _this2.$t("InvalidData"), _this2.$t("Failed"));
        _this2.SubmitProcessing = false;
      });
    },
    Selected_Project: function Selected_Project(value) {
      if (value === null) {
        this.task.project_id = "";
      }
    },
    Selected_Team: function Selected_Team(value) {
      if (value === null) {
        this.assigned_employees = [];
      }
    },
    Selected_Status: function Selected_Status(value) {
      if (value === null) {
        this.task.status = "";
      }
    },
    Selected_Company: function Selected_Company(value) {
      if (value === null) {
        this.task.company_id = "";
      }
      this.employees = [];
      this.task.assigned_to = [];
      this.Get_employees_by_company(value);
    },
    //---------------------- Get_employees_by_company ------------------------------\\
    Get_employees_by_company: function Get_employees_by_company(value) {
      var _this3 = this;
      axios.get("/Get_employees_by_company?id=" + value).then(function (_ref2) {
        var data = _ref2.data;
        return _this3.employees = data;
      });
    },
    //---------------------------------------Get deposit Elements ------------------------------\\
    GetElements: function GetElements() {
      var _this4 = this;
      var id = this.$route.params.id;
      axios.get("tasks/".concat(id, "/edit")).then(function (response) {
        _this4.task = response.data.task;
        _this4.projects = response.data.projects;
        _this4.companies = response.data.companies;
        _this4.employees = response.data.employees;
        _this4.assigned_employees = response.data.assigned_employees;
        _this4.isLoading = false;
      })["catch"](function (response) {
        setTimeout(function () {
          _this4.isLoading = false;
        }, 500);
      });
    }
  },
  //----------------------------- Created function-------------------
  created: function created() {
    this.GetElements();
  }
});

/***/ }),

/***/ "./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/lib/loaders/templateLoader.js??ruleSet[1].rules[2]!./node_modules/vue-loader/lib/index.js??vue-loader-options!./resources/src/views/app/pages/tasks/Edit_task.vue?vue&type=template&id=14d2d46c":
/*!**************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/lib/loaders/templateLoader.js??ruleSet[1].rules[2]!./node_modules/vue-loader/lib/index.js??vue-loader-options!./resources/src/views/app/pages/tasks/Edit_task.vue?vue&type=template&id=14d2d46c ***!
  \**************************************************************************************************************************************************************************************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   render: () => (/* binding */ render),
/* harmony export */   staticRenderFns: () => (/* binding */ staticRenderFns)
/* harmony export */ });
var render = function render() {
  var _vm = this,
    _c = _vm._self._c;
  return _c("div", {
    staticClass: "main-content"
  }, [_c("breadcumb", {
    attrs: {
      page: _vm.$t("Edit_Task"),
      folder: _vm.$t("Tasks")
    }
  }), _vm._v(" "), _vm.isLoading ? _c("div", {
    staticClass: "loading_page spinner spinner-primary mr-3"
  }) : _vm._e(), _vm._v(" "), !_vm.isLoading ? _c("validation-observer", {
    ref: "ref_edit_task"
  }, [_c("b-form", {
    on: {
      submit: function submit($event) {
        $event.preventDefault();
        return _vm.Submit_Task.apply(null, arguments);
      }
    }
  }, [_c("b-row", [_c("b-col", {
    attrs: {
      lg: "12",
      md: "12",
      sm: "12"
    }
  }, [_c("b-card", [_c("b-row", [_c("b-col", {
    attrs: {
      lg: "4",
      md: "6",
      sm: "12"
    }
  }, [_c("validation-provider", {
    attrs: {
      name: "Title",
      rules: {
        required: true
      }
    },
    scopedSlots: _vm._u([{
      key: "default",
      fn: function fn(validationContext) {
        return [_c("b-form-group", {
          attrs: {
            label: _vm.$t("title") + " " + "*"
          }
        }, [_c("b-form-input", {
          attrs: {
            placeholder: _vm.$t("Enter_Task_Title"),
            state: _vm.getValidationState(validationContext),
            "aria-describedby": "title-feedback",
            label: "Task Title"
          },
          model: {
            value: _vm.task.title,
            callback: function callback($$v) {
              _vm.$set(_vm.task, "title", $$v);
            },
            expression: "task.title"
          }
        }), _vm._v(" "), _c("b-form-invalid-feedback", {
          attrs: {
            id: "title-feedback"
          }
        }, [_vm._v(_vm._s(validationContext.errors[0]))])], 1)];
      }
    }], null, false, 1865549125)
  })], 1), _vm._v(" "), _c("b-col", {
    attrs: {
      lg: "4",
      md: "6",
      sm: "12"
    }
  }, [_c("validation-provider", {
    attrs: {
      name: "start_date",
      rules: {
        required: true
      }
    },
    scopedSlots: _vm._u([{
      key: "default",
      fn: function fn(validationContext) {
        return [_c("b-form-group", {
          attrs: {
            label: _vm.$t("start_date") + " " + "*"
          }
        }, [_c("b-form-input", {
          attrs: {
            state: _vm.getValidationState(validationContext),
            "aria-describedby": "start_date-feedback",
            type: "date"
          },
          model: {
            value: _vm.task.start_date,
            callback: function callback($$v) {
              _vm.$set(_vm.task, "start_date", $$v);
            },
            expression: "task.start_date"
          }
        }), _vm._v(" "), _c("b-form-invalid-feedback", {
          attrs: {
            id: "start_date-feedback"
          }
        }, [_vm._v(_vm._s(validationContext.errors[0]))])], 1)];
      }
    }], null, false, 959996867)
  })], 1), _vm._v(" "), _c("b-col", {
    attrs: {
      lg: "4",
      md: "6",
      sm: "12"
    }
  }, [_c("validation-provider", {
    attrs: {
      name: "end_date",
      rules: {
        required: true
      }
    },
    scopedSlots: _vm._u([{
      key: "default",
      fn: function fn(validationContext) {
        return [_c("b-form-group", {
          attrs: {
            label: _vm.$t("Finish_Date") + " " + "*"
          }
        }, [_c("b-form-input", {
          attrs: {
            state: _vm.getValidationState(validationContext),
            "aria-describedby": "end_date-feedback",
            type: "date"
          },
          model: {
            value: _vm.task.end_date,
            callback: function callback($$v) {
              _vm.$set(_vm.task, "end_date", $$v);
            },
            expression: "task.end_date"
          }
        }), _vm._v(" "), _c("b-form-invalid-feedback", {
          attrs: {
            id: "end_date-feedback"
          }
        }, [_vm._v(_vm._s(validationContext.errors[0]))])], 1)];
      }
    }], null, false, 1051971551)
  })], 1), _vm._v(" "), _c("b-col", {
    attrs: {
      lg: "4",
      md: "6",
      sm: "12"
    }
  }, [_c("validation-provider", {
    attrs: {
      name: "Project"
    },
    scopedSlots: _vm._u([{
      key: "default",
      fn: function fn(_ref) {
        var valid = _ref.valid,
          errors = _ref.errors;
        return _c("b-form-group", {
          attrs: {
            label: _vm.$t("Project") + " " + "*"
          }
        }, [_c("v-select", {
          "class": {
            "is-invalid": !!errors.length
          },
          attrs: {
            state: errors[0] ? false : valid ? true : null,
            reduce: function reduce(label) {
              return label.value;
            },
            placeholder: _vm.$t("Choose_Project"),
            options: _vm.projects.map(function (projects) {
              return {
                label: projects.title,
                value: projects.id
              };
            })
          },
          on: {
            input: _vm.Selected_Project
          },
          model: {
            value: _vm.task.project_id,
            callback: function callback($$v) {
              _vm.$set(_vm.task, "project_id", $$v);
            },
            expression: "task.project_id"
          }
        }), _vm._v(" "), _c("b-form-invalid-feedback", [_vm._v(_vm._s(errors[0]))])], 1);
      }
    }], null, false, 2688915400)
  })], 1), _vm._v(" "), _c("b-col", {
    attrs: {
      lg: "4",
      md: "6",
      sm: "12"
    }
  }, [_c("validation-provider", {
    attrs: {
      name: "Company"
    },
    scopedSlots: _vm._u([{
      key: "default",
      fn: function fn(_ref2) {
        var valid = _ref2.valid,
          errors = _ref2.errors;
        return _c("b-form-group", {
          attrs: {
            label: _vm.$t("Company") + " " + "*"
          }
        }, [_c("v-select", {
          "class": {
            "is-invalid": !!errors.length
          },
          attrs: {
            state: errors[0] ? false : valid ? true : null,
            reduce: function reduce(label) {
              return label.value;
            },
            placeholder: _vm.$t("Choose_Company"),
            options: _vm.companies.map(function (companies) {
              return {
                label: companies.name,
                value: companies.id
              };
            })
          },
          on: {
            input: _vm.Selected_Company
          },
          model: {
            value: _vm.task.company_id,
            callback: function callback($$v) {
              _vm.$set(_vm.task, "company_id", $$v);
            },
            expression: "task.company_id"
          }
        }), _vm._v(" "), _c("b-form-invalid-feedback", [_vm._v(_vm._s(errors[0]))])], 1);
      }
    }], null, false, 17890031)
  })], 1), _vm._v(" "), _c("b-col", {
    attrs: {
      lg: "4",
      md: "6",
      sm: "12"
    }
  }, [_c("validation-provider", {
    attrs: {
      name: "Assigned_Employees"
    },
    scopedSlots: _vm._u([{
      key: "default",
      fn: function fn(_ref3) {
        var valid = _ref3.valid,
          errors = _ref3.errors;
        return _c("b-form-group", {
          attrs: {
            label: _vm.$t("Assigned_Employees")
          }
        }, [_c("v-select", {
          "class": {
            "is-invalid": !!errors.length
          },
          attrs: {
            state: errors[0] ? false : valid ? true : null,
            reduce: function reduce(label) {
              return label.value;
            },
            placeholder: _vm.$t("Choose_Team"),
            options: _vm.employees.map(function (employees) {
              return {
                label: employees.username,
                value: employees.id
              };
            })
          },
          on: {
            input: _vm.Selected_Team
          },
          model: {
            value: _vm.assigned_employees,
            callback: function callback($$v) {
              _vm.assigned_employees = $$v;
            },
            expression: "assigned_employees"
          }
        }), _vm._v(" "), _c("b-form-invalid-feedback", [_vm._v(_vm._s(errors[0]))])], 1);
      }
    }], null, false, 743477489)
  })], 1), _vm._v(" "), _c("b-col", {
    attrs: {
      lg: "4",
      md: "6",
      sm: "12"
    }
  }, [_c("validation-provider", {
    attrs: {
      name: "Status",
      rules: {
        required: true
      }
    },
    scopedSlots: _vm._u([{
      key: "default",
      fn: function fn(_ref4) {
        var valid = _ref4.valid,
          errors = _ref4.errors;
        return _c("b-form-group", {
          attrs: {
            label: _vm.$t("Status") + " " + "*"
          }
        }, [_c("v-select", {
          "class": {
            "is-invalid": !!errors.length
          },
          attrs: {
            state: errors[0] ? false : valid ? true : null,
            reduce: function reduce(label) {
              return label.value;
            },
            placeholder: _vm.$t("Choose_Status"),
            options: [{
              label: "Not Started",
              value: "not_started"
            }, {
              label: "In Progress",
              value: "progress"
            }, {
              label: "Cancelled",
              value: "cancelled"
            }, {
              label: "On Hold",
              value: "hold"
            }, {
              label: "Completed",
              value: "completed"
            }]
          },
          on: {
            input: _vm.Selected_Status
          },
          model: {
            value: _vm.task.status,
            callback: function callback($$v) {
              _vm.$set(_vm.task, "status", $$v);
            },
            expression: "task.status"
          }
        }), _vm._v(" "), _c("b-form-invalid-feedback", [_vm._v(_vm._s(errors[0]))])], 1);
      }
    }], null, false, 1658158517)
  })], 1), _vm._v(" "), _c("b-col", {
    attrs: {
      lg: "8",
      md: "8",
      sm: "12"
    }
  }, [_c("validation-provider", {
    attrs: {
      name: "Description"
    },
    scopedSlots: _vm._u([{
      key: "default",
      fn: function fn(_ref5) {
        var valid = _ref5.valid,
          errors = _ref5.errors;
        return _c("b-form-group", {
          attrs: {
            label: _vm.$t("Details")
          }
        }, [_c("textarea", {
          directives: [{
            name: "model",
            rawName: "v-model",
            value: _vm.task.description,
            expression: "task.description"
          }],
          staticClass: "form-control",
          "class": {
            "is-invalid": !!errors.length
          },
          attrs: {
            state: errors[0] ? false : valid ? true : null,
            rows: "4",
            placeholder: _vm.$t("Afewwords")
          },
          domProps: {
            value: _vm.task.description
          },
          on: {
            input: function input($event) {
              if ($event.target.composing) return;
              _vm.$set(_vm.task, "description", $event.target.value);
            }
          }
        }), _vm._v(" "), _c("b-form-invalid-feedback", [_vm._v(_vm._s(errors[0]))])], 1);
      }
    }], null, false, 2843828298)
  })], 1), _vm._v(" "), _c("b-col", {
    attrs: {
      md: "12"
    }
  }, [_c("b-form-group", [_c("b-button", {
    attrs: {
      variant: "primary",
      type: "submit",
      disabled: _vm.SubmitProcessing
    }
  }, [_c("i", {
    staticClass: "i-Yes me-2 font-weight-bold"
  }), _vm._v(" " + _vm._s(_vm.$t("submit")))]), _vm._v(" "), _vm.SubmitProcessing ? _vm._m(0) : _vm._e()], 1)], 1)], 1)], 1)], 1)], 1)], 1)], 1) : _vm._e()], 1);
};
var staticRenderFns = [function () {
  var _vm = this,
    _c = _vm._self._c;
  return _c("div", {
    staticClass: "typo__p"
  }, [_c("div", {
    staticClass: "spinner sm spinner-primary mt-3"
  })]);
}];
render._withStripped = true;


/***/ }),

/***/ "./resources/src/views/app/pages/tasks/Edit_task.vue":
/*!***********************************************************!*\
  !*** ./resources/src/views/app/pages/tasks/Edit_task.vue ***!
  \***********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _Edit_task_vue_vue_type_template_id_14d2d46c__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./Edit_task.vue?vue&type=template&id=14d2d46c */ "./resources/src/views/app/pages/tasks/Edit_task.vue?vue&type=template&id=14d2d46c");
/* harmony import */ var _Edit_task_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./Edit_task.vue?vue&type=script&lang=js */ "./resources/src/views/app/pages/tasks/Edit_task.vue?vue&type=script&lang=js");
/* harmony import */ var _node_modules_vue_loader_lib_runtime_componentNormalizer_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! !../../../../../../node_modules/vue-loader/lib/runtime/componentNormalizer.js */ "./node_modules/vue-loader/lib/runtime/componentNormalizer.js");





/* normalize component */
;
var component = (0,_node_modules_vue_loader_lib_runtime_componentNormalizer_js__WEBPACK_IMPORTED_MODULE_2__["default"])(
  _Edit_task_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_1__["default"],
  _Edit_task_vue_vue_type_template_id_14d2d46c__WEBPACK_IMPORTED_MODULE_0__.render,
  _Edit_task_vue_vue_type_template_id_14d2d46c__WEBPACK_IMPORTED_MODULE_0__.staticRenderFns,
  false,
  null,
  null,
  null
  
)

/* hot reload */
if (false) { var api; }
component.options.__file = "resources/src/views/app/pages/tasks/Edit_task.vue"
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (component.exports);

/***/ }),

/***/ "./resources/src/views/app/pages/tasks/Edit_task.vue?vue&type=script&lang=js":
/*!***********************************************************************************!*\
  !*** ./resources/src/views/app/pages/tasks/Edit_task.vue?vue&type=script&lang=js ***!
  \***********************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _node_modules_babel_loader_lib_index_js_clonedRuleSet_5_use_0_node_modules_vue_loader_lib_index_js_vue_loader_options_Edit_task_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../../../../node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!../../../../../../node_modules/vue-loader/lib/index.js??vue-loader-options!./Edit_task.vue?vue&type=script&lang=js */ "./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/lib/index.js??vue-loader-options!./resources/src/views/app/pages/tasks/Edit_task.vue?vue&type=script&lang=js");
 /* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (_node_modules_babel_loader_lib_index_js_clonedRuleSet_5_use_0_node_modules_vue_loader_lib_index_js_vue_loader_options_Edit_task_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_0__["default"]); 

/***/ }),

/***/ "./resources/src/views/app/pages/tasks/Edit_task.vue?vue&type=template&id=14d2d46c":
/*!*****************************************************************************************!*\
  !*** ./resources/src/views/app/pages/tasks/Edit_task.vue?vue&type=template&id=14d2d46c ***!
  \*****************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   render: () => (/* reexport safe */ _node_modules_babel_loader_lib_index_js_clonedRuleSet_5_use_0_node_modules_vue_loader_lib_loaders_templateLoader_js_ruleSet_1_rules_2_node_modules_vue_loader_lib_index_js_vue_loader_options_Edit_task_vue_vue_type_template_id_14d2d46c__WEBPACK_IMPORTED_MODULE_0__.render),
/* harmony export */   staticRenderFns: () => (/* reexport safe */ _node_modules_babel_loader_lib_index_js_clonedRuleSet_5_use_0_node_modules_vue_loader_lib_loaders_templateLoader_js_ruleSet_1_rules_2_node_modules_vue_loader_lib_index_js_vue_loader_options_Edit_task_vue_vue_type_template_id_14d2d46c__WEBPACK_IMPORTED_MODULE_0__.staticRenderFns)
/* harmony export */ });
/* harmony import */ var _node_modules_babel_loader_lib_index_js_clonedRuleSet_5_use_0_node_modules_vue_loader_lib_loaders_templateLoader_js_ruleSet_1_rules_2_node_modules_vue_loader_lib_index_js_vue_loader_options_Edit_task_vue_vue_type_template_id_14d2d46c__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../../../../node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!../../../../../../node_modules/vue-loader/lib/loaders/templateLoader.js??ruleSet[1].rules[2]!../../../../../../node_modules/vue-loader/lib/index.js??vue-loader-options!./Edit_task.vue?vue&type=template&id=14d2d46c */ "./node_modules/babel-loader/lib/index.js??clonedRuleSet-5.use[0]!./node_modules/vue-loader/lib/loaders/templateLoader.js??ruleSet[1].rules[2]!./node_modules/vue-loader/lib/index.js??vue-loader-options!./resources/src/views/app/pages/tasks/Edit_task.vue?vue&type=template&id=14d2d46c");


/***/ })

}]);