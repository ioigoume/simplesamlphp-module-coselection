$(document).ready(function () {
    $(jQuery.unique(
        //create an array with the names of the groups
        $('INPUT:radio')
            .map(function(i,e){
                return $(e).attr('name') }
            ).get()
    ))
    //interate the array (array with unique names of groups)
    .each(function(i,e){
        //make the first radio-button of each group checked
        $('INPUT:radio[name="'+e+'"]:visible:first')
            .attr('checked', true);
    });

    $('button[name="yes"]').click(function (e) {
        var groups = $('div.attrvalue');

        // If the user has filled in inputs, show loader and fill hiden
        // `attributeSelection` input with user data
        console.log(groups.length > 0);
        if (groups.length > 0) {
            var data = {};
            var inputs = 0
            groups.each(function (key, group) {
                var name = $(group).attr('name');
                console.log(name);
                var value = new Array();
                var input = $('input[name="' + name + '"]:checked');
                $('input[name="' + name + '"]:checked').each(function(){
                    value.push($(this).val());
                    console.log($(this).val());
                });
                data[name] = value;
            });
            


            $('input[name="attributeSelection"]').val(JSON.stringify(data));
            $('#loader').show();

            $('#loader').siblings().hide();
        }
    })
});