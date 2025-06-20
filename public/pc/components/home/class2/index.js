define([
    'text!./index.html',
    'css!./index.css'
], function (html) {
    return {
        props: {
            recommend: {
                type: Object,
                default: function () {
                    return {};
                }
            },
			showMore: {
				type: Boolean,
				default: function () {
				    return true;
				}
			}
        },
        data: function () {
            return {
				searchValue: '',
				currentURL: window.location.pathname,
				selected: 1
			};
        },
        methods: {
			onSearch: function (hot) {
			    if (!hot && !this.searchValue) {
			        return;
			    }
			    if (hot) {
			        this.searchValue = hot;
			    }
			    if (this.currentURL.includes('/web/special/special_cate')) {
			        if (this.selected === 1) {
			            this.$emit('submit-search', this.searchValue);
			        } else {
			            window.location.assign(this.$router.material_list + '?search=' + this.searchValue);
			        }
			    } else if (this.currentURL.includes('/web/material/material_list')) {
			        if (this.selected === 2) {
			            this.$emit('submit-search', this.searchValue);
			        } else {
			            window.location.assign(this.$router.special_cate + '?search=' + this.searchValue);
			        }
			    } else {
			        window.location.assign((this.selected === 1 ? this.$router.special_cate : this.$router.material_list) + '?search=' + this.searchValue);
			    }
			},
		},
        template: html
    };
});