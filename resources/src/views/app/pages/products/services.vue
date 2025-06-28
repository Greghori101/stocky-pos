<template>
  <div class="main-content">
    <breadcumb
      :page="$t('Services')"
      :folder="$t('Products')"
    />

    <div
      v-if="isLoading"
      class="loading_page spinner spinner-primary mr-3"
    ></div>
    <b-card
      class="wrapper"
      v-if="!isLoading"
    >
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="services"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{
          enabled: true,
          placeholder: $t('Search_this_table'),
        }"
        :select-options="{
          enabled: true,
          clearSelectionText: '',
        }"
        @on-selected-rows-change="selectionChanged"
        :pagination-options="{
          enabled: true,
          mode: 'records',
          nextLabel: 'next',
          prevLabel: 'prev',
        }"
        styleClass="table-hover tableOne vgt-table"
      >
        <div slot="selected-row-actions">
          <button
            class="btn btn-danger btn-sm"
            @click="delete_by_selected()"
          >
            {{ $t('Del') }}
          </button>
        </div>
        <div
          slot="table-actions"
          class="mt-2 mb-3"
        >
          <b-button
            @click="New_service()"
            class="btn-rounded"
            variant="btn btn-primary btn-icon m-1"
          >
            <i class="i-Add"></i>
            {{ $t('Add') }}
          </b-button>
        </div>

        <template
          slot="table-row"
          slot-scope="props"
        >
          <span v-if="props.column.field == 'actions'">
            <a
              @click="Edit_service(props.row)"
              title="Edit"
              v-b-tooltip.hover
            >
              <i class="i-Edit text-25 text-success"></i>
            </a>
            <a
              title="Delete"
              v-b-tooltip.hover
              @click="Remove_Service(props.row.id)"
            >
              <i class="i-Close-Window text-25 text-danger"></i>
            </a>
          </span>
          <span v-else-if="props.column.field == 'image'">
            <b-img
              thumbnail
              height="50"
              width="50"
              fluid
              :src="'/images/services/' + props.row.image"
              alt="image"
            ></b-img>
          </span>
        </template>
      </vue-good-table>
    </b-card>

    <validation-observer ref="Create_Service">
      <b-modal
        hide-footer
        size="md"
        id="New_Service"
        :title="editmode ? $t('Edit') : $t('Add')"
      >
        <b-form @submit.prevent="Submit_Service">
          <b-row>
            <!-- Price service -->
            <b-col md="12">
              <validation-provider
                name="Price service"
                :rules="{ required: true }"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('Priceservice') + ' ' + '*'">
                  <b-form-input
                    :placeholder="$t('Enter_Price_service')"
                    :state="getValidationState(validationContext)"
                    aria-describedby="Price-feedback"
                    label="Price"
                    type="number"
                    v-model="service.price"
                  ></b-form-input>
                  <b-form-invalid-feedback id="Price-feedback">{{
                    validationContext.errors[0]
                  }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Unit per minute -->
            <b-col md="12">
              <validation-provider
                name="Unit per minute"
                :rules="{ required: true }"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('UnitPerMinute') + ' ' + '*'">
                  <b-form-input
                    :placeholder="$t('Enter_unit_service')"
                    :state="getValidationState(validationContext)"
                    aria-describedby="Unit-feedback"
                    label="Unit Per Minute"
                    type="number"
                    v-model="service.unit_per_minute"
                  ></b-form-input>
                  <b-form-invalid-feedback id="Unit-feedback">{{
                    validationContext.errors[0]
                  }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <b-col
              md="6"
              class="mb-2"
            >
              <validation-provider
                name="Image"
                ref="Image"
                rules="mimes:image/*"
              >
                <b-form-group
                  slot-scope="{ validate, valid, errors }"
                  label="Product Image"
                >
                  <input
                    :state="errors[0] ? false : valid ? true : null"
                    :class="{ 'is-invalid': !!errors.length }"
                    @change="onFileSelected"
                    label="Choose Image"
                    type="file"
                  />
                  <b-form-invalid-feedback id="Image-feedback">{{
                    errors[0]
                  }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <b-col
              md="12"
              class="mt-3"
            >
              <b-button
                variant="primary"
                type="submit"
                :disabled="SubmitProcessing"
                ><i class="i-Yes me-2 font-weight-bold"></i>
                {{ $t('submit') }}</b-button
              >
              <div
                v-once
                class="typo__p"
                v-if="SubmitProcessing"
              >
                <div class="spinner sm spinner-primary mt-3"></div>
              </div>
            </b-col>
          </b-row>
        </b-form>
      </b-modal>
    </validation-observer>
  </div>
</template>

<script>
import NProgress from 'nprogress'

export default {
  metaInfo: {
    title: 'Service',
  },
  data() {
    return {
      isLoading: true,
      SubmitProcessing: false,
      serverParams: {
        columnFilters: {},
        sort: {
          field: 'id',
          type: 'desc',
        },
        page: 1,
        perPage: 10,
      },
      selectedIds: [],
      totalRows: '',
      search: '',
      limit: '10',
      services: [],
      editmode: false,

      service: {
        id: '',
        price: '',
        unit_per_minute: '',
        image: '',
      },
    }
  },

  computed: {
    columns() {
      return [
        {
          label: this.$t('image'),
          field: 'image',
          type: 'image',
          html: true,
          tdClass: 'text-left',
          thClass: 'text-left',
        },
        {
          label: this.$t('Priceservice'),
          field: 'price',
          tdClass: 'text-left',
          thClass: 'text-left',
        },
        {
          label: this.$t('UnitPerMinute'),
          field: 'unit_per_minute',
          tdClass: 'text-left',
          thClass: 'text-left',
        },
        {
          label: this.$t('Action'),
          field: 'actions',
          html: true,
          tdClass: 'text-right',
          thClass: 'text-right',
          sortable: false,
        },
      ]
    },
  },

  methods: {
    //---- update Params Table
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps)
    },

    //---- Event Page Change
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage })
        this.Get_Services(currentPage)
      }
    },

    //---- Event Per Page Change
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage
        this.updateParams({ page: 1, perPage: currentPerPage })
        this.Get_Services(1)
      }
    },

    //---- Event Select Rows
    selectionChanged({ selectedRows }) {
      this.selectedIds = []
      selectedRows.forEach((row, index) => {
        this.selectedIds.push(row.id)
      })
    },

    //---- Event on Sort Change
    onSortChange(params) {
      this.updateParams({
        sort: {
          type: params[0].type,
          field: params[0].field,
        },
      })
      this.Get_Services(this.serverParams.page)
    },

    //---- Event on Search

    onSearch(value) {
      this.search = value.searchTerm
      this.Get_Services(this.serverParams.page)
    },

    //---- Validation State Form

    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null
    },

    //------------- Submit Validation Create & Edit Service
    Submit_Service() {
      this.$refs.Create_Service.validate().then((success) => {
        if (!success) {
          this.makeToast(
            'danger',
            this.$t('Please_fill_the_form_correctly'),
            this.$t('Failed')
          )
        } else {
          if (!this.editmode) {
            this.Create_Service()
          } else {
            this.Update_Service()
          }
        }
      })
    },

    //------------------------------ Event Upload Image -------------------------------\\
    async onFileSelected(e) {
      const { valid } = await this.$refs.Image.validate(e)

      if (valid) {
        this.service.image = e.target.files[0]
      } else {
        this.service.image = ''
      }
    },
    //------ Toast
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true,
      })
    },

    //------------------------------ Modal  (create service) -------------------------------\\
    New_service() {
      this.reset_Form()
      this.editmode = false
      this.$bvModal.show('New_Service')
    },

    //------------------------------ Modal (Update service) -------------------------------\\
    Edit_service(service) {
      this.Get_Services(this.serverParams.page)
      this.reset_Form()
      this.service = service
      this.editmode = true
      this.$bvModal.show('New_Service')
    },

    //--------------------------Get ALL Services & Sub service ---------------------------\\

    Get_Services(page) {
      // Start the progress bar.
      NProgress.start()
      NProgress.set(0.1)
      axios
        .get(
          'services?page=' +
            page +
            '&SortField=' +
            this.serverParams.sort.field +
            '&SortType=' +
            this.serverParams.sort.type +
            '&search=' +
            this.search +
            '&limit=' +
            this.limit
        )
        .then((response) => {
          this.services = response.data.data
          this.totalRows = response.data.total

          // Complete the animation of theprogress bar.
          NProgress.done()
          this.isLoading = false
        })
        .catch((response) => {
          // Complete the animation of theprogress bar.
          NProgress.done()
          setTimeout(() => {
            this.isLoading = false
          }, 500)
        })
    },

    //----------------------------------Create new Service ----------------\\
    Create_Service() {
      this.SubmitProcessing = true
      const formData = new FormData()
      formData.append('unit_per_minute', this.service.unit_per_minute)
      formData.append('price', this.service.price)
      formData.append('image', this.service.image)

      axios
        .post('services', formData)
        .then((response) => {
          this.SubmitProcessing = false
          Fire.$emit('Event_Service')
          this.makeToast(
            'success',
            this.$t('Create.TitleCat'),
            this.$t('Success')
          )
        })
        .catch((error) => {
          this.SubmitProcessing = false
          this.makeToast('danger', this.$t('InvalidData'), this.$t('Failed'))
        })
    },

    //---------------------------------- Update Service ----------------\\
    Update_Service() {
      this.SubmitProcessing = true
      axios
        .put('services/' + this.service.id, {
          unit_per_minute: this.service.unit_per_minute,
          price: this.service.price,
        })
        .then((response) => {
          this.SubmitProcessing = false
          Fire.$emit('Event_Service')
          this.makeToast(
            'success',
            this.$t('Update.TitleCat'),
            this.$t('Success')
          )
        })
        .catch((error) => {
          this.SubmitProcessing = false
          this.makeToast('danger', this.$t('InvalidData'), this.$t('Failed'))
        })
    },

    //--------------------------- reset Form ----------------\\

    reset_Form() {
      this.service = {
        id: '',
        unit_per_minute: '',
        price: '',
      }
    },

    //--------------------------- Remove Service----------------\\
    Remove_Service(id) {
      this.$swal({
        title: this.$t('Delete.Title'),
        text: this.$t('Delete.Text'),
        type: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        cancelButtonText: this.$t('Delete.cancelButtonText'),
        confirmButtonText: this.$t('Delete.confirmButtonText'),
      }).then((result) => {
        if (result.value) {
          axios
            .delete('services/' + id)
            .then(() => {
              this.$swal(
                this.$t('Delete.Deleted'),
                this.$t('Delete.CatDeleted'),
                'success'
              )

              Fire.$emit('Delete_Service')
            })
            .catch(() => {
              this.$swal(
                this.$t('Delete.Failed'),
                this.$t('Delete.Therewassomethingwronge'),
                'warning'
              )
            })
        }
      })
    },

    //---- Delete service by selection

    delete_by_selected() {
      this.$swal({
        title: this.$t('Delete.Title'),
        text: this.$t('Delete.Text'),
        type: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        cancelButtonText: this.$t('Delete.cancelButtonText'),
        confirmButtonText: this.$t('Delete.confirmButtonText'),
      }).then((result) => {
        if (result.value) {
          // Start the progress bar.
          NProgress.start()
          NProgress.set(0.1)
          axios
            .post('services/delete/by_selection', {
              selectedIds: this.selectedIds,
            })
            .then(() => {
              this.$swal(
                this.$t('Delete.Deleted'),
                this.$t('Delete.CatDeleted'),
                'success'
              )

              Fire.$emit('Delete_Service')
            })
            .catch(() => {
              // Complete the animation of theprogress bar.
              setTimeout(() => NProgress.done(), 500)
              this.$swal(
                this.$t('Delete.Failed'),
                this.$t('Delete.Therewassomethingwronge'),
                'warning'
              )
            })
        }
      })
    },
  }, //end Methods

  //----------------------------- Created function-------------------

  created: function () {
    this.Get_Services(1)

    Fire.$on('Event_Service', () => {
      setTimeout(() => {
        this.Get_Services(this.serverParams.page)
        this.$bvModal.hide('New_Service')
      }, 500)
    })

    Fire.$on('Delete_Service', () => {
      setTimeout(() => {
        this.Get_Services(this.serverParams.page)
      }, 500)
    })
  },
}
</script>
