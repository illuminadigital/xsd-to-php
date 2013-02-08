<?xml version="1.0" encoding="UTF-8"?>
<!-- Copyright 2010 Mike Bevz <myb@mikebevz.com> Licensed under the Apache 
	License, Version 2.0 (the "License"); you may not use this file except in 
	compliance with the License. You may obtain a copy of the License at http://www.apache.org/licenses/LICENSE-2.0 
	Unless required by applicable law or agreed to in writing, software distributed 
	under the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES 
	OR CONDITIONS OF ANY KIND, either express or implied. See the License for 
	the specific language governing permissions and limitations under the License. -->
<xsl:stylesheet version="1.0"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:xsd="http://www.w3.org/2001/XMLSchema"
	xmlns:exslt="http://exslt.org/common">

	<xsl:template
		match="//*[local-name()='schema' and namespace-uri()='http://www.w3.org/2001/XMLSchema']">

		<xsl:variable name="targetNamespace" select="@targetNamespace" />

		<!-- Generate classes for each element with data type as extention -->
		<xsdschema>
			<classes>

				<xsl:for-each
					select="*[local-name()='element' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']">
					
					<xsl:call-template name="processElement">
						<xsl:with-param name="targetNamespace" select="$targetNamespace" />
						<xsl:with-param name="isTopLevel" select="'true'" />
					</xsl:call-template>
				</xsl:for-each>
				
				<xsl:for-each select="*/*//*[local-name()='element' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema' and not(@type)]">
					
					<xsl:call-template name="processElement">
						<xsl:with-param name="targetNamespace" select="$targetNamespace" />
						<xsl:with-param name="isTopLevel" select="'false'" />
					</xsl:call-template>
				</xsl:for-each>

				<xsl:for-each
					select="*[local-name()='complexType' and namespace-uri()='http://www.w3.org/2001/XMLSchema']">
					<xsl:variable name="classSimpleType"
						select="substring-after(current()/*[local-name()='simpleContent']/*[local-name()='extension']/@base, ':')" />
					<xsl:variable name="classSimpleTypeNs"
						select="substring-before(current()/*[local-name()='simpleContent']/*[local-name()='extension']/@base, ':')" />
					<xsl:choose>
						<xsl:when test="@namespace">
							<class debug="1.2-1" name="{@name}" type="{$classSimpleType}"
								typeNamespace="{$classSimpleTypeNs}" namespace="{@namespace}">
								<xsl:apply-templates />
							</class>
						</xsl:when>
						<xsl:otherwise>
							<class debug="1.2-2" name="{@name}" type="{$classSimpleType}"
								typeNamespace="{$classSimpleTypeNs}" namespace="{$targetNamespace}">
								<xsl:apply-templates />
							</class>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:for-each>

				<xsl:for-each
					select="//*[local-name()='simpleType' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']">
					<xsl:variable name="extends" select="current()//*[local-name()='restriction']/@base" />
					<xsl:variable name="type">
						<xsl:choose>
							<xsl:when test="count($extends) = 1 and contains($extends, ':')">
								<xsl:value-of select="substring-after($extends, ':')" />
							</xsl:when>
							<xsl:when test="current()/*[local-name()='union']">
								<!-- Not ideal handling, but -->
								<xsl:text>anyType</xsl:text>
							</xsl:when>
							<xsl:otherwise>
								<xsl:value-of select="$extends" />
							</xsl:otherwise>
						</xsl:choose>
					</xsl:variable>
					<xsl:choose>
						<xsl:when test="$type='anyType'">
							<class debug="1.3-3 - AnyType" name="{@name}" extends="{$type}"
								type="{$type}" namespace="{@namespace}" dummyProperty="true">
								<property debug="Dummy-Property-2" xmlType="element"
									name="value" type="{$type}" 
									typeNamespace="#default#">
								<xsl:apply-templates
									select="*[local-name()='annotation' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']" />
								</property>
							</class>
						</xsl:when>
						<xsl:when test="@namespace">
							<xsl:variable name="typeNamespace">
								<xsl:choose>
									<xsl:when test="contains($extends, ':')">
										<xsl:variable name="ns" select="substring-before($extends, ':')" />
										<xsl:for-each select="ancestor-or-self::*/@*[name()=concat('xmlns:', $ns)]">
											<xsl:value-of select="." />
										</xsl:for-each>
									</xsl:when>
									<xsl:when test="/*[local-name()='schema']">
										<xsl:for-each select="/*[local-name()='schema']">
											<xsl:value-of select="namespace-uri()" />
										</xsl:for-each>
									</xsl:when>
								</xsl:choose>
							</xsl:variable>
							<class debug="1.3-1" name="{@name}" extends="{$extends}" type="{$type}"
								namespace="{@namespace}" dummyProperty="true">
								<property debug="Dummy-Property-1" xmlType="element"
									name="value" type="{$type}" namespace="{@namespace}"
									typeNamespace="{$typeNamespace}">
								<xsl:apply-templates
									select="*[local-name()='restriction' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']" />
								<xsl:apply-templates
									select="*[local-name()='annotation' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']" />
								</property>
							</class>
						</xsl:when>
						<xsl:otherwise>
							<class debug="1.3-2 - ERROR No Namespace" name="{@name}" extends="{$type}"
								type="{$type}" namespace="{@namespace}" dummyProperty="true">
								<property debug="Dummy-Property-2" xmlType="element"
									name="value" type="{$type}" namespace="#default#"
									>
									<xsl:attribute name="typeNamespace">
										<xsl:choose>
											<xsl:when test="/*[local-name()='schema']">
												<xsl:for-each select="/*[local-name()='schema']">
													<xsl:value-of select="namespace-uri()" />
												</xsl:for-each>
											</xsl:when>
											<xsl:otherwise>
												<xsl:text>#default#</xsl:text>
											</xsl:otherwise>
										</xsl:choose>
									</xsl:attribute>
								
								<xsl:apply-templates
									select="*[local-name()='restriction' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']" />
								<xsl:apply-templates
									select="*[local-name()='annotation' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']" />
								</property>
							</class>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:for-each>
			</classes>
		</xsdschema>

	</xsl:template>


	<!-- Annotation -->
	<xsl:template
		match="*[local-name()='annotation' and namespace-uri()='http://www.w3.org/2001/XMLSchema']">
		<docs>
			<xsl:apply-templates />
		</docs>
	</xsl:template>

	<!-- Sequence -->
	<xsl:template
		match="*[local-name()='sequence' and namespace-uri()='http://www.w3.org/2001/XMLSchema']">
		<xsl:apply-templates />
	</xsl:template>
	
	<!-- Group -->
	<xsl:template 
		match="*[local-name()='group' and @ref and namespace-uri()='http://www.w3.org/2001/XMLSchema']">
		
		<xsl:variable name="groupLocalname" select="substring-after(@ref, ':')" />
		<xsl:variable name="groupNamespace" select="substring-before(@ref, ':')" />
		
		<xsl:for-each select="//*[local-name()='group' and @name=$groupLocalname]/*[local-name()='sequence']">
			<xsl:apply-templates />
		</xsl:for-each>
	</xsl:template>

	<!-- any -->
	<xsl:template
		match="*[local-name()='any' and namespace-uri()='http://www.w3.org/2001/XMLSchema']">
		<property debug="any" targetNamespace="{@namespace}">
			<xsl:attribute name="name">
				<xsl:text>*</xsl:text>
				<xsl:choose>
					<xsl:when test="@name">
						<xsl:value-of select="@name" />
					</xsl:when>
					<xsl:otherwise>
						<xsl:value-of select="local-name()" />
					</xsl:otherwise>
				</xsl:choose>
			</xsl:attribute>
			<xsl:attribute name="namespace">
				<xsl:choose>
					<xsl:when test="ancestor::*[@namespace]">
						<xsl:value-of select="ancestor::*[@namespace][1]/@namespace" />
					</xsl:when>
					<xsl:otherwise>
						<xsl:value-of select="ancestor::*[@targetNamespace][1]/@targetNamespace" />
					</xsl:otherwise>
				</xsl:choose>
				
			</xsl:attribute>
			
			<xsl:apply-templates />
		</property>
	</xsl:template>

	<!-- element -->
	<xsl:template
		match="*[local-name()='element' and namespace-uri()='http://www.w3.org/2001/XMLSchema']">
		<xsl:choose>
			<xsl:when test="contains(@ref,':')">
				<xsl:variable name="type" select="substring-after(@ref,':')" />
				<xsl:variable name="ns" select="substring-before(@ref,':')" />
				
				<xsl:variable name="nspace">
					<xsl:choose>
						<xsl:when test="ancestor-or-self::*/@*[name()=concat('xmlns:', $ns)]">
							<xsl:for-each select="ancestor-or-self::*/@*[name()=concat('xmlns:', $ns)]">
								<xsl:value-of select="." />
							</xsl:for-each>
						</xsl:when>
						<xsl:otherwise>
							<xsl:text>#default#</xsl:text>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:variable>
				
				<property debug="refElement" xmlType="element"
					name="{$type}" type="{$type}"
					namespace="{$nspace}" minOccurs="{@minOccurs}"
					maxOccurs="{@maxOccurs}">
								<xsl:apply-templates
									select="*[local-name()='restriction' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']" />
								<xsl:apply-templates
									select="*[local-name()='annotation' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']" />
				</property>
			</xsl:when>
			<xsl:when test="@ref and not(contains(@ref,':'))">
				<xsl:choose>
					<xsl:when test="../../@namespace">
						<property debug="refElement-ParentNS" xmlType="element"
							name="{@ref}" type="{@ref}" minOccurs="{@minOccurs}" namespace="{../../@namespace}"
							maxOccurs="{@maxOccurs}">
								<xsl:apply-templates
									select="*[local-name()='restriction' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']" />
								<xsl:apply-templates
									select="*[local-name()='annotation' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']" />
						</property>
					</xsl:when>
					<xsl:otherwise>
						<property debug="refElement-NoNS" xmlType="element" name="{@ref}"
							type="{@ref}" minOccurs="{@minOccurs}" maxOccurs="{@maxOccurs}">
								<xsl:apply-templates
									select="*[local-name()='restriction' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']" />
								<xsl:apply-templates
									select="*[local-name()='annotation' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']" />
						</property>
					</xsl:otherwise>
				</xsl:choose>
			</xsl:when>
			<xsl:when test="@name">
				<xsl:choose>
					<xsl:when test="contains(@type, ':')">
						<xsl:choose>
							<xsl:when test="../../@namespace">
								<property debug="nameElement-TypeColonNamespace"
									xmlType="element" name="{@name}" type="{substring-after(@type, ':')}"
									minOccurs="{@minOccurs}"
									typeNamespace="{substring-before(@type, ':')}" maxOccurs="{@maxOccurs}">
								
									<xsl:variable name="type" select="substring-after(@type, ':')" />
									<xsl:variable name="lowertype" select="translate($type, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnoqrstuvwxyz')" />
									<xsl:attribute name="namespace">
										<xsl:choose>
											<xsl:when test="//*[@name=$type][@namespace]">
												<xsl:for-each select="//*[@name=$type][@namespace][1]">
													<xsl:value-of select="@namespace" />
												</xsl:for-each>
											</xsl:when>
											<xsl:when test="//*[@name=$lowertype][@namespace]">
												<xsl:for-each select="//*[@name=$lowertype][@namespace][1]">
													<xsl:value-of select="@namespace" />
												</xsl:for-each>
											</xsl:when>
											<xsl:when test="../../@namespace">
												<xsl:value-of select="../../@namespace" />
											</xsl:when>
											<xsl:when test="/schema[@xmlns]">
												<xsl:value-of select="/schema/@namespace" />
											</xsl:when>
										</xsl:choose>
									</xsl:attribute>
									
								<xsl:apply-templates
									select="*[local-name()='restriction' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']" />
								<xsl:apply-templates
									select="*[local-name()='annotation' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']" />
								</property>
							</xsl:when>
							<xsl:otherwise>
								<property debug="nameElement-TypeColonNoNamespace"
									xmlType="element" name="{@name}" type="{substring-after(@type, ':')}"
									minOccurs="{@minOccurs}"
									typeNamespace="{substring-before(@type, ':')}" maxOccurs="{@maxOccurs}">

									<xsl:attribute name="namespace">
										<xsl:variable name="type" select="substring-after(@type, ':')" />
										<xsl:variable name="lowertype" select="translate($type, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnoqrstuvwxyz')" />
										<xsl:choose>
											<xsl:when test="//*[@name=$type][@namespace]">
												<xsl:for-each select="//*[@name=$type][@namespace][1]">
													<xsl:value-of select="@namespace" />
												</xsl:for-each>
											</xsl:when>
											<xsl:when test="//*[@name=$lowertype][@namespace]">
												<xsl:for-each select="//*[@name=$lowertype][@namespace][1]">
													<xsl:value-of select="@namespace" />
												</xsl:for-each>
											</xsl:when>
											<xsl:otherwise>
												<xsl:text>#default#</xsl:text>
											</xsl:otherwise>
										</xsl:choose>
									</xsl:attribute>
								<xsl:apply-templates
									select="*[local-name()='restriction' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']" />
								<xsl:apply-templates
									select="*[local-name()='annotation' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']" />
								</property>
							</xsl:otherwise>
						</xsl:choose>
					</xsl:when>
					<xsl:when test="@type">
						<property debug="nameElement-TypeNoColon" xmlType="element"
							name="{@name}" type="{@type}"
							typeNamespace="#default#" minOccurs="{@minOccurs}" maxOccurs="{@maxOccurs}">
								<xsl:attribute name="namespace">
									<xsl:choose>
										<xsl:when test="/*[local-name()='schema']">
											<xsl:for-each select="/*[local-name()='schema']">
												<xsl:value-of select="namespace-uri()" />
											</xsl:for-each>
										</xsl:when>
										<xsl:otherwise>
											<xsl:text>#default#</xsl:text>
										</xsl:otherwise>
									</xsl:choose>
								</xsl:attribute>
								<xsl:apply-templates
									select="*[local-name()='restriction' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']" />
								<xsl:apply-templates
									select="*[local-name()='annotation' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']" />
						</property>

					</xsl:when>
					<xsl:otherwise>
						<property debug="nameElement-NoType" xmlType="element"
							name="{@name}" type="{@name}" namespace="#default#"
							typeNamespace="#default#" minOccurs="{@minOccurs}" maxOccurs="{@maxOccurs}">
								<xsl:apply-templates
									select="*[local-name()='restriction' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']" />
								<xsl:apply-templates
									select="*[local-name()='annotation' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']" />
						</property>
					</xsl:otherwise>
				</xsl:choose>
			</xsl:when>

		</xsl:choose>
	</xsl:template>

	<!-- restriction -->
	<xsl:template
		match="*[local-name()='restriction' and namespace-uri()='http://www.w3.org/2001/XMLSchema']">
		<xsl:variable name="targetNamespace">
			<xsl:choose>
				<xsl:when test="@targetNamespace">
					<xsl:value-of select="@targetNamespace" />
				</xsl:when>
				<xsl:otherwise>
					<xsl:for-each select="/*[local-name()='schema']">
						<xsl:value-of select="namespace-uri()" />
					</xsl:for-each>
				</xsl:otherwise>
			</xsl:choose>
		</xsl:variable>
		<xsl:choose>
			<xsl:when test="contains(@base, ':')">
				<extends debug="ColonBase" name="{substring-after(@base,':')}">
					<xsl:variable name="type" select="substring-after(@base,':')" />
					<xsl:variable name="nspace" select="substring-before(@base,':')" />
					<xsl:attribute name="namespace">
						<xsl:for-each select="//*[@name=$type]/ancestor-or-self::*/@*[name()=concat('xmlns:', $nspace)]">
							<xsl:value-of select="." />
						</xsl:for-each>
					</xsl:attribute>
				</extends>
			</xsl:when>
			<xsl:otherwise>
				<extends debug="NoColonBase" name="{@base}" namespace="{$targetNamespace}" />
			</xsl:otherwise>
		</xsl:choose>
		<restrictions>
			<xsl:apply-templates />
		</restrictions>
	</xsl:template>

	<!-- minInclusive -->
	<xsl:template
		match="*[local-name()='minInclusive' and namespace-uri()='http://www.w3.org/2001/XMLSchema']">
		<xsl:variable name="tag" select="local-name()" />
		<restriction name="{$tag}" value="{@value}" />
	</xsl:template>

	<!-- maxInclusive -->
	<xsl:template
		match="*[local-name()='maxInclusive' and namespace-uri()='http://www.w3.org/2001/XMLSchema']">
		<xsl:variable name="tag" select="local-name()" />
		<restriction name="{$tag}" value="{@value}" />
	</xsl:template>

	<!-- minExclusive -->
	<xsl:template
		match="*[local-name()='minExclusive' and namespace-uri()='http://www.w3.org/2001/XMLSchema']">
		<xsl:variable name="tag" select="local-name()" />
		<restriction name="{$tag}" value="{@value}" />
	</xsl:template>

	<!-- maxExclusive -->
	<xsl:template
		match="*[local-name()='maxExclusive' and namespace-uri()='http://www.w3.org/2001/XMLSchema']">
		<xsl:variable name="tag" select="local-name()" />
		<restriction name="{$tag}" value="{@value}" />
	</xsl:template>

	<!-- minLength -->
	<xsl:template
		match="*[local-name()='minLength' and namespace-uri()='http://www.w3.org/2001/XMLSchema']">
		<xsl:variable name="tag" select="local-name()" />
		<restriction name="{$tag}" value="{@value}" />
	</xsl:template>

	<!-- maxLength -->
	<xsl:template
		match="*[local-name()='maxLength' and namespace-uri()='http://www.w3.org/2001/XMLSchema']">
		<xsl:variable name="tag" select="local-name()" />
		<restriction name="{$tag}" value="{@value}" />
	</xsl:template>

	<!-- pattern -->
	<xsl:template
		match="*[local-name()='pattern' and namespace-uri()='http://www.w3.org/2001/XMLSchema']">
		<xsl:variable name="tag" select="local-name()" />
		<restriction name="{$tag}" value="{@value}" />
	</xsl:template>

	<!-- enumeration -->
	<xsl:template
		match="*[local-name()='enumeration' and namespace-uri()='http://www.w3.org/2001/XMLSchema']">
		<xsl:variable name="tag" select="local-name()" />
		<enumeration value="{@value}">
			<xsl:apply-templates
				select="*[local-name()='annotation' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']" />
		</enumeration>
	</xsl:template>

	<!-- Simplecontent -->
	<xsl:template
		match="*[local-name()='simpleContent' and namespace-uri()='http://www.w3.org/2001/XMLSchema']">
		<xsl:apply-templates />
	</xsl:template>

	<xsl:template
		match="*[local-name()='extension' and namespace-uri()='http://www.w3.org/2001/XMLSchema']">
		<xsl:if test="@base">
			<!-- @todo Crappy: Checking if the @base namespace is XMLSchema. it slows 
				down everything so bad. Will be using 'xsd' for check now, improve in future -->
			<xsl:variable name="nspace" select="substring-before(@base,':')" />
			<xsl:if test="$nspace!='xsd'">


				<xsl:choose>
					<xsl:when test="contains(@base, ':')">
						<extends debug="Extends3" name="{substring-after(@base,':')}">
							<xsl:attribute name="namespace">
								<xsl:variable name="baseName" select="substring-after(@base, ':')" />
								<xsl:for-each select="//*[@name=$baseName]">
									<xsl:value-of select="@namespace" />
								</xsl:for-each>
							</xsl:attribute>
						</extends>
						<xsl:apply-templates />
					</xsl:when>
					<xsl:otherwise>
						<!-- Not sure about namespace here... -->
						<extends debug="Extends4" name="{@base}" namespace="{@base}" />
						<xsl:apply-templates />
					</xsl:otherwise>
				</xsl:choose>
			</xsl:if>
			<xsl:if test="$nspace='xsd'">
				<xsl:apply-templates />
			</xsl:if>

		</xsl:if>

	</xsl:template>

	<!-- Attribute -->

	<xsl:template
		match="*[local-name()='attribute' and namespace-uri()='http://www.w3.org/2001/XMLSchema']">
		<xsl:choose>
			<xsl:when test="@name">
				<xsl:choose>
					<xsl:when test="contains(@type, ':')">
						<property debug="attribute-TypeNs" xmlType="attribute"
							name="{@name}" type="{substring-after(@type, ':')}"
							typeNamespace="{substring-before(@type, ':')}" default="{@default}"
							use="{@use}">
							
							<xsl:variable name="type" select="substring-after(@type, ':')" />
							<xsl:variable name="lowertype" select="translate($type, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnoqrstuvwxyz')" />
							
							<xsl:if test="//*[@name=$type or @name=$lowertype][@namespace]">
								<xsl:attribute name="namespace">
									<xsl:choose>
										<xsl:when test="//*[@name=$type][@namespace]">
											<xsl:for-each select="//*[@name=$type][@namespace][1]">
												<xsl:value-of select="@namespace" />
											</xsl:for-each>
										</xsl:when>
										<xsl:when test="//*[@name=$lowertype][@namespace]">
											<xsl:for-each select="//*[@name=$lowertype][@namespace][1]">
												<xsl:value-of select="@namespace" />
											</xsl:for-each>
										</xsl:when>
										<xsl:otherwise>
											<xsl:text>#default#</xsl:text>
										</xsl:otherwise>
									</xsl:choose>
								</xsl:attribute>							
							</xsl:if>
							
								<xsl:apply-templates
									select="*[local-name()='restriction' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']" />
								<xsl:apply-templates
									select="*[local-name()='annotation' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']" />
						</property>
					</xsl:when>
					<xsl:otherwise>
						<property debug="attribute-TypeNoNs" xmlType="attribute"
							name="{@name}" type="{@type}" default="{@default}" use="{@use}">
								<xsl:apply-templates
									select="*[local-name()='restriction' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']" />
								<xsl:apply-templates
									select="*[local-name()='annotation' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']" />
						</property>
					</xsl:otherwise>

				</xsl:choose>

			</xsl:when>
			<xsl:when test="@ref">
				<xsl:variable name="attRef"
					select="//*[local-name()='attribute' and namespace-uri()='http://www.w3.org/2001/XMLSchema'][@name=current()/@ref]/@type" />
				<xsl:choose>
					<xsl:when test="contains($attRef, ':')">
						<property debug="attribute-Ref-1" xmlType="attribute"
							name="{@ref}" type="{substring-after($attRef,':')}" namespace="{substring-before($attRef,':')}"
							default="{@default}" use="{@use}">
								<xsl:apply-templates
									select="*[local-name()='restriction' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']" />
								<xsl:apply-templates
									select="*[local-name()='annotation' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']" />
						</property>
					</xsl:when>
					<xsl:otherwise>
						<property debug="attribute-Ref-2" xmlType="attribute"
							name="{@ref}" type="{$attRef}" default="{@default}" use="{@use}">
								<xsl:apply-templates
									select="*[local-name()='restriction' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']" />
								<xsl:apply-templates
									select="*[local-name()='annotation' and
					namespace-uri()='http://www.w3.org/2001/XMLSchema']" />
						</property>
					</xsl:otherwise>
				</xsl:choose>

			</xsl:when>
			<!-- @todo otherwise -->
		</xsl:choose>
	</xsl:template>

	<!-- Documentation -->
	<xsl:template
		match="*[local-name()='documentation' and namespace-uri()='http://www.w3.org/2001/XMLSchema']">
		<xsl:choose>
			<xsl:when test="child::*">
				<xsl:for-each select="child::*">
					<xsl:choose>
						<xsl:when test="local-name()='Component'">
							<xsl:for-each select="child::*">
								<doc name="{local-name()}">
									<xsl:value-of select="current()" />
								</doc>
							</xsl:for-each>
						</xsl:when>
						<xsl:otherwise>
							<doc name="{local-name()}">
								<xsl:value-of select="current()" />
							</doc>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:for-each>
			</xsl:when>
			<xsl:otherwise>
				<doc name="Definition">
					<xsl:value-of select="current()" />
				</doc>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>
	
	<xsl:template name="processElement">
		<xsl:param name="targetNamespace" />
		<xsl:param name="isTopLevel" select="'false'" />
		
					<xsl:choose>
						<xsl:when test="@namespace">
							<xsl:choose>
								<xsl:when test="contains(@type, ':')">
									<xsl:variable name="ns" select="substring-before(@type,':')" />
									<xsl:variable name="nspace">
										<xsl:for-each select="ancestor-or-self::*/@*[name()=concat('xmlns:', $ns)]">
											<xsl:value-of select="." />
										</xsl:for-each>
									</xsl:variable>
									
									<class debug="1.0.1" name="{@name}" type="{substring-after(@type,':')}"
										namespace="{$nspace}">
										<extends debug="1.0Extend" name="{substring-after(@type,':')}">
											<xsl:attribute name="namespace">
												<xsl:value-of select="$nspace"/>
											</xsl:attribute>
										</extends>
										<xsl:apply-templates />
									</class>
								</xsl:when>
								<xsl:otherwise>
									<class debug="1.0" name="{@name}" namespace="{@namespace}">
										<extends debug="1.0Extend" name="{@type}" />
										<xsl:apply-templates />
									</class>
								</xsl:otherwise>
							</xsl:choose>
						</xsl:when>
						<xsl:when test="*[local-name='complexType']/@name=''">
							<class debug="1.2" name="{@name}" namespace="{@namespace}">
								<extends debug="1.0Extend" name="{@type}" />
								<xsl:apply-templates />
							</class>
						</xsl:when>
						<xsl:when test="*[local-name='simpleType']/@name=''">
							<xsl:variable name="baseType" select="current()/*[local-name()='restriction']/@base" />
							<class debug="1.3" name="{@name}" extends="{$baseType}" namespace="{@namespace}">
								<xsl:apply-templates />
							</class>
						</xsl:when>
						<xsl:otherwise>
							<xsl:choose>
								<xsl:when test="contains(@type, ':')">
									<class debug="1.4" name="{@name}" type="{substring-after(@type,':')}"
										namespace="{$targetNamespace}">
										<extends debug="1.1Extend" name="{substring-after(@type,':')}">
											<xsl:attribute name="namespace">
											<xsl:variable name="type" select="substring-after(@type, ':')" />
											<xsl:variable name="lowertype" select="translate($type, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnoqrstuvwxyz')" />
											<xsl:choose>
												<xsl:when test="//*[@name=$type][@namespace]">
													<xsl:for-each select="//*[@name=$type][@namespace][1]">
														<xsl:value-of select="@namespace" />
													</xsl:for-each>
												</xsl:when>
												<xsl:when test="//*[@name=$lowertype][@namespace]">
													<xsl:for-each select="//*[@name=$lowertype][@namespace][1]">
														<xsl:value-of select="@namespace" />
													</xsl:for-each>
												</xsl:when>
												<xsl:otherwise>
													<xsl:text>#default#</xsl:text>
												</xsl:otherwise>
											</xsl:choose>
										</xsl:attribute>
										</extends>
										<xsl:apply-templates />
									</class>
								</xsl:when>
								<xsl:when test="ancestor::*[local-name()='schema']/*[local-name()='annotation']/*[local-name() = 'documentation']/*[local-name() = 'type-id']">
									<class debug="1.4-2" name="{@name}" type="{@type}"
										namespace="{$targetNamespace}" id="{ancestor::*[local-name()='schema']/*[local-name()='annotation']/*[local-name() = 'documentation']/*[local-name() = 'type-id']}" refName="{ancestor::*[local-name()='schema']/*[local-name()='annotation']/*[local-name() = 'documentation']/*[local-name() = 'type-name']}">
										<extends debug="1.2Extend" name="com.microsoft.wc.thing.Thing" />
										<xsl:apply-templates />
									</class>
								</xsl:when>
								<xsl:otherwise>
									<class debug="1.4-1" name="{@name}" type="{@type}"
										namespace="{$targetNamespace}">
										<extends debug="1.2Extend" name="{@type}" />
										<xsl:apply-templates />
									</class>
								</xsl:otherwise>
							</xsl:choose>
						</xsl:otherwise>
					</xsl:choose>
	</xsl:template>

</xsl:stylesheet>