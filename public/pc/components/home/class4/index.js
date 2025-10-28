define([
    'text!./index.html',
    'css!./index.css'
], function(html) {
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
			},
			from: {
				type: String,
				default: function () {
				    return '';
				}
			},
			labels: {
				type: String,
				default: function () {
				    return '';
				}
			},
			school_id: {
				type: Number,
				default: function () {
				    return 0;
				}
			}
        },
        template: html
    };
});