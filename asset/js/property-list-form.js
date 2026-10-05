/**
 * PropertyList form element (AgileThemeTools\Form\Element\PropertyList).
 *
 * The listed properties are lozenges that can be removed and, when sortable, dragged into order. Every change is
 * written back to the hidden input as a comma-separated list of terms, which is what the form saves.
 */
(function ($) {
    'use strict';

    $(document).ready(function () {

        $('.agile-property-list').each(function () {
            const container = $(this);
            const input = container.children('input[type="hidden"]');
            const picker = container.find('.agile-property-list-picker');
            const list = container.find('.agile-property-list-items');
            const empty = container.find('.agile-property-list-empty');
            const sortable = String(container.data('sortable')) === '1';
            const removeLabel = container.data('removeLabel');

            const listedTerms = function () {
                return list.children('.agile-property-list-item').map(function () {
                    return $(this).attr('data-term');
                }).get();
            };

            // Save the list to the hidden input, and stop the picker offering properties already listed.
            const sync = function () {
                const terms = listedTerms();
                input.val(terms.join(','));
                empty.prop('hidden', terms.length > 0);
                picker.find('option[value!=""]').each(function () {
                    $(this).prop('disabled', terms.includes(this.value));
                });
                picker.trigger('chosen:updated');
            };

            const addItem = function (term, display) {
                const item = $('<li class="agile-property-list-item"></li>').attr('data-term', term);
                if (sortable) {
                    item.append('<span class="sortable-handle" aria-hidden="true"></span>');
                }
                item.append($('<span class="agile-property-list-label"></span>').text(display));
                item.append($('<span class="agile-property-list-term"></span>').text(term));
                item.append($('<button type="button" class="o-icon-delete agile-property-list-remove"></button>')
                    .attr({title: removeLabel, 'aria-label': removeLabel + ' ' + display}));
                list.append(item);
            };

            picker.on('change', function () {
                const option = picker.find('option:selected');
                if (option.val()) {
                    addItem(option.val(), option.data('display'));
                }
                picker.val('');
                sync();
            });

            container.on('click', '.agile-property-list-remove', function () {
                $(this).closest('.agile-property-list-item').remove();
                sync();
            });

            container.find('.agile-property-list-add-all').on('click', function () {
                const terms = listedTerms();
                picker.find('option[value!=""]').each(function () {
                    if (!terms.includes(this.value)) {
                        addItem(this.value, $(this).data('display'));
                    }
                });
                sync();
            });

            container.find('.agile-property-list-clear').on('click', function () {
                list.empty();
                sync();
            });

            if (sortable && typeof Sortable !== 'undefined') {
                new Sortable(list[0], {
                    draggable: '.agile-property-list-item',
                    handle: '.sortable-handle',
                    onEnd: sync
                });
            }

            sync();
        });
    });
})(jQuery);
