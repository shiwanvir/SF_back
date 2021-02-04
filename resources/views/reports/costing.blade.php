@extends('reports.common.template')

@section('title')
    Costing Details
@stop

@section('content')   

<div class="container">
	<div class="row">
		<div class="col-md-8 col-sm-offset-2 form-holder">
			
			
			<div class="col-md-12">
				<div class="logo">
					<img src="{{ URL::asset('assets\images\logo.jpg') }}"/>
				</div>
				<div class="address"> 
					@foreach ($company as $row)
					<div class="company-name">{{ $row->company_name }}</div>
					<div class="company-address">{{ $row->loc_name }}</div>
					<div class="company-address">{{ $row->loc_address_1 }},{{ $row->loc_address_2 }},{{ $row->country_description }}</div>
					<div class="company-contact"><strong>Tel :</strong> {{ $row->loc_phone }} / <strong>Fax :</strong> {{ $row->loc_fax }}</div>
					<div class="company-contact"><strong>Email :</strong> {{ $row->loc_email }} / <strong>Web :</strong> {{ $row->loc_web }}</div>
					@endforeach
				</div>
			</div>

			@foreach ($headers as $header)
			<div class="col-md-12">
				<div class="title">COST SHEET (COST ID -{{ $header->id }})</div> 
			</div>


			<div class="col-md-12">
				<div class="style-details">
					<table class="table table-borderless">
					  <tr>
					    <th width="17%">Customer</th>
					    <th width="2%">:</th>
					    <td width="31%">{{ $header->customer_name }}</td>
					    <th width="17%">BOM Stage</th>
					    <th width="2%">:</th>
					    <td width="31%">{{ $header->bom_stage_description }}</td>
					  </tr>

					  <tr>
					    <th>Costing by</th>
					    <th>:</th>
					    <td>{{ $header->user_name }}</td>
					    <th>Costing Date</th>
					    <th>:</th>
					    <td>{{ $header->created_date2 }}</td>
					  </tr>

					  <tr>
					    <th>Color Type</th>
					    <th>:</th>
					    <td>{{ $header->color_option }}</td>
					    <th>Division</th>
					    <th>:</th>
					    <td>{{ $header->division_description }}</td>
					  </tr>

					  <tr>
					    <th>Style No</th>
					    <th>:</th>
					    <td>{{ $header->style_no }}</td>
					    <th>Style Name</th>
					    <th>:</th>
					    <td>{{ $header->style_description }}</td>
					  </tr>

					  <tr>
					    <th>Season</th>
					    <th>:</th>
					    <td>{{ $header->season_name }}</td>
					    <th>Approval No</th>
					    <th>:</th>
					    <td>{{ $header->revision_no }}</td>
					  </tr>

					  <tr>
					    <th>Approval Date</th>
					    <th>:</th>
					    <td>{{ $header->last_app_by }}</td>
					    <th>Buy</th>
					    <th>:</th>
					    <td>{{ $header->buy_name }}</td>
					  </tr>

					  <tr>
					    <th>Status</th>
					    <th>:</th>
					    <td>{{ $header->status }}</td>
					    <th>Design Source</th>
					    <th>:</th>
					    <td>{{ $header->design_source_name }}</td>
					  </tr>

					</table>
				</div>

				<div class="style-image">
					<div class="text-right">
						@if($header->image!='')
							<img src="{{ URL::to('/assets/styleImage/') }}/{{ $header->image }}" alt="" class="img-thumbnail image-holder">
						@else
							<img alt="" class="img-thumbnail image-holder">
						@endif
					</div>
				</div>
	
			</div>


			<div class="col-md-12">
				<table class="table table-bordered details">
					<tr class="green-row">
						<th width="20%">FOB</th>
						<th width="20%">Planned Efficiency</th>
						<th width="20%">Upcharge</th>
						<th width="20%">CPM</th>
						<th width="20%">CPSM</th>
					</tr>

					<tr>
						<td>{{ $header->fob }}</td>
						<td>{{ $header->planned_efficiency }}</td>
						<td>{{ $header->upcharge }}</td>
						<td>{{ $header->cost_per_min }}</td>
						<td>{{ $header->cost_per_std_min }}</td>
					</tr>

					<tr class="green-row">
						<th>EPM</th>
						<th>NPM</th>
						<th>CPUM</th>
						<th>CPM Factory</th>
						<th>Corporate Cost</th>
					</tr>

					<tr>
						<td>{{ $header->epm }}</td>
						<td>{{ $header->np_margine }}</td>
						<td>{{ $header->cost_per_utilised_min }}</td>
						<td>{{ $header->cpm_factory }}</td>
						<td>{{ $header->coperate_cost }}</td>
					</tr>

					<tr class="green-row">
						<th>Total RM Cost</th>
						<th>Labour Cost</th>
						<th>Finance Cost</th>					
						<th>Finance Charges</th>
						<th>CPM Front End</th>
					</tr>

					<tr>
						<td>{{ $header->total_rm_cost }}</td>
						<td>{{ $header->labour_cost }}</td>
						<td>{{ $header->finance_cost }}</td>
						<td>{{ $header->finance_charges }}</td>
						<td>{{ $header->cpm_front_end }}</td>						
					</tr>
				
				</table>				
				@endforeach
				<hr>
			</div>


			<div class="col-md-12">
				<table class="table table-striped table-bordered table-no-padding">
				  <thead>
				    <tr>
				      <th width="20px">#</th>
				      <th>Item Description</th>
				      <th>Comp</th>
				      <th>Origin</th>
				      <th>Unit</th>
				      <th>Net&nbsp;Con</th>
				      <th>Gross&nbsp;Con</th>
				      <th>Wastage</th>
				      <th>Freight</th>
				      <th>Surcharge</th>
				      <th>UP</th>
				      <th>TC PC</th>
				    </tr>
				  </thead>
				  <tbody>
				  	@foreach($categories as $category)
					  	<tr>
					       <td colspan="12" class="main-category">{{ $category->category_name }}</td>
					    </tr>
					    @php 
					    	$c=1;
					    	$cat_sum=0;
					    @endphp
					    @foreach($details as $detail)
					    	@if($category->category_id==$detail->category_id)
							    <tr>
							      <td class="text-center">{{ $c }}</td>
							      <td>{{ $detail->master_description }}</td>
							      <td>{{ $detail->product_component_description }}</td>
							      <td>{{ $detail->origin_type }}</td>
							      <td>{{ $detail->uom_description }}</td>
							      <td class="text-right">{{ $detail->net_consumption }}</td>
							      <td class="text-right">{{ $detail->gross_consumption }}</td>
							      <td class="text-right">{{ $detail->wastage }}%</td>
							      <td class="text-right">{{ $detail->freight_charges }}</td>
							      <td class="text-right">{{ $detail->surcharge }}</td>
							      <td class="text-right">{{ $detail->unit_price }}</td>
							      <td class="text-right">{{ $detail->total_cost }}</td>
							    </tr>

							 @php
								$cat_sum += $detail->total_cost;
							@endphp   

							@endif
						@php 
							$c++;
						@endphp
						@endforeach
						<tr>
					      <td colspan="11" class="cat-total">TOTAL {{ $category->category_name }} COST</td>
					      <td class="text-bold cat-total text-right bottom-border">{{ number_format($cat_sum, 4, '.', '') }}</td>					   
					    </tr>	
					@endforeach

					@if(sizeof($fng_categories)>0)
					
						<tr>
					      <td colspan="12" class="main-category">FG PACKING TRIMS</td>
					    </tr>			    

						@foreach($fng_categories as $fng_category)
					  	<tr>
					       <td colspan="12" class="main-category">{{ $fng_category->category_name }}</td>
					    </tr>
					    @php 
					    	$c=1;
					    	$fng_cat_sum=0;
					    @endphp
					    @foreach($fng_details as $fng_detail)
					    	@if($fng_category->category_id==$fng_detail->category_id)
							    <tr>
							      <td class="text-center">{{ $c }}</td>
							      <td>{{ $fng_detail->master_description }}</td>
							      <td>{{ $fng_detail->product_component_description }}</td>
							      <td>{{ $fng_detail->origin_type }}</td>
							      <td>{{ $fng_detail->uom_description }}</td>
							      <td class="text-right">{{ $fng_detail->net_consumption }}</td>
							      <td class="text-right">{{ $fng_detail->gross_consumption }}</td>
							      <td class="text-right">{{ $fng_detail->wastage }}%</td>
							      <td class="text-right">{{ $fng_detail->freight_charges }}</td>
							      <td class="text-right">{{ $fng_detail->surcharge }}</td>
							      <td class="text-right">{{ $fng_detail->unit_price }}</td>
							      <td class="text-right">{{ $fng_detail->total_cost }}</td>
							    </tr>

								 @php
									$fng_cat_sum += $fng_detail->total_cost;
								@endphp   

								@endif
							@php 
								$c++;
							@endphp
							@endforeach
							<tr>
						      <td colspan="11" class="cat-total">TOTAL FG {{ $fng_category->category_name }} COST</td>
						      <td class="text-bold cat-total text-right bottom-border">{{ number_format($fng_cat_sum, 4, '.', '') }}</td>					   
						    </tr>	
						@endforeach
					@endif	


					@foreach ($headers as $header)
						<tr>
							<td colspan="12">&nbsp;</td>
						</tr>
					    <tr >
					      <td colspan="11" class="cat-total" style="background-color: #e6e6e6;">Total RM Cost</td>
					      <td class="text-right cat-total bottom-border" style="background-color: #e6e6e6;">{{ number_format($header->total_rm_cost,4) }}</td>					   
					    </tr>
					    <tr>
					      <td colspan="11">Labour / Sub Contracting Cost</td>
					      <td class="text-right">{{ $header->labour_cost }}</td>					   
					    </tr>
					    <tr>
					      <td colspan="11" class="cat-total" style="background-color: #e6e6e6;">Total Manufacturing Cost</td>
					      <td class="text-right cat-total bottom-border" style="background-color: #e6e6e6;">{{ $header->total_rm_cost+$header->labour_cost }}</td>					   
					    </tr>
					    <tr>
					      <td colspan="11">Finance Cost</td>
					      <td class="text-right">{{ $header->finance_cost }}</td>					   
					    </tr>			    
					    <tr>
					      <td colspan="11">Corporate Cost</td>
					      <td class="text-right">{{ $header->coperate_cost }}</td>					   
					    </tr>
					    <tr>
					      <td colspan="11">Upcharge (Reason for Upcharge : {{ $header->upcharge_reason }})</td>
					      <td class="text-right">{{ $header->upcharge }}</td>					   
					    </tr>
					    <tr>
					      <td colspan="11" class="cat-total" style="background-color: #e6e6e6;">Total Cost</td>
					      <td class="text-right cat-total bottom-border" style="background-color: #e6e6e6;">{{ number_format($header->total_cost,4) }}</td>					   
					    </tr>
					    <tr>
					      <td colspan="11">Total FOB</td>
					      <td class="text-right">{{ $header->fob }}</td>					   
					    </tr>
					    <tr>
					      <td colspan="11">SMV</td>
					      <td class="text-right">{{ $header->total_smv }}</td>					   
					    </tr>
					    <tr>
					      <td colspan="11">EPM</td>
					      <td class="text-right">{{ $header->epm }}</td>					   
					    </tr>
					    <tr>
					      <td colspan="11">NP</td>
					      <td class="text-right">{{ $header->np_margine }}%</td>					   
					    </tr>
					    <tr>
					      <td colspan="11">Planned Efficiency</td>
					      <td class="text-right">{{ $header->planned_efficiency }}%</td>					   
					    </tr>
					    <tr>
					      <td colspan="11">CPM Factory</td>
					      <td class="text-right">{{ $header->cpm_factory }}</td>					   
					    </tr>		    	 
					@endforeach

				  </tbody>
				</table>	
			</div>


			<div class="col-md-6 pre-ratio">			
				<div class="ratio-title">Percentage Ratio</div>
				<table class="table table-striped table-bordered table-no-padding">
				  <thead>
				    <tr>
				      <th>RM Cost</th>
				      <th>%</th>
				      <th>Cost / PC</th>
				    </tr>
				  </thead>
				  <tbody>
				  	@foreach($ratios as $ratio)
				    <tr>
				      <td>{{ $ratio->category_name }}</td>
				      <td class="text-right">{{ number_format($ratio->cat_sum*100/$ratio->tot_sum, 4, '.', '') }}</td>
				      <td class="text-right">{{ $ratio->cat_sum }}</td>
				    </tr>
				    @endforeach
				  </tbody>
				</table>
			</div>
			

		</div>
	</div>
</div>

@stop

