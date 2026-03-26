/* Wrova Admin JS — Task 2.1 / 4.1 實作 */
( function ( $, wpApiFetch ) {
    'use strict';

    window.Wrova = {
        rest: wrovaData.restUrl,
        nonce: wrovaData.nonce,

        fetch( endpoint, options = {} ) {
            return wpApiFetch( {
                url: this.rest + endpoint,
                ...options,
            } );
        },
    };

} )( jQuery, wp.apiFetch );
